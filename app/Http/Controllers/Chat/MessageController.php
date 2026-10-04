<?php

namespace App\Http\Controllers\Chat;

use App\Chat\ChatRealtime;
use App\Chat\ConversationLoader;
use App\Chat\EmailAnnouncements;
use App\Chat\MessageGrouping;
use App\Chat\MessagePins;
use App\Chat\MessageReactions;
use App\Chat\PostMessage;
use App\Enums\ChatMessageType;
use App\Enums\ChatReaction;
use App\Enums\ReportReason;
use App\Events\Chat\ChatGroupChanged;
use App\Http\Controllers\Chat\Concerns\ResolvesChatMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatMessageReport;
use App\Models\Formation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Envoyer, supprimer, épingler et signaler un message.
 */
class MessageController extends Controller
{
    use ResolvesChatMember;

    public function __construct(private ChatRealtime $realtime) {}

    public function store(StoreChatMessageRequest $request, Formation $formation, PostMessage $post): JsonResponse|RedirectResponse
    {
        $viewer = $this->viewer($request, $formation);

        if (! $viewer->canPost()) {
            throw ValidationException::withMessages(['body' => (string) $viewer->postBlockedReason()]);
        }

        $message = $post->handle(
            $viewer->group,
            $viewer->member,
            $viewer->user,
            ChatMessageType::from($request->string('type')->toString()),
            $request->string('body')->toString(),
            $request->file('attachment'),
            $request->integer('reply_to_id') ?: null,
            $request->integer('duration') ?: null,
        );

        // Annonce importante envoyée aussi par e-mail (administrateurs seulement ; jamais pour un élève).
        $email = $request->boolean('email_members') && EmailAnnouncements::canUse($viewer->user)
            ? app(EmailAnnouncements::class)->send($message->setRelation('group', $viewer->group), $viewer->user)
            : null;

        if (! $request->expectsJson()) {
            return redirect()->route('chat.show', $formation)->with('status', $email === null ? null : self::emailStatus($email));
        }

        return response()->json(['id' => $message->id, 'email' => $email === null ? null : [...$email, 'text' => self::emailStatus($email)]], 201);
    }

    /**
     * Avant l'envoi : nombre de membres qui recevront l'e-mail, ou raison du refus (administrateurs seulement).
     */
    public function emailPreview(Request $request, Formation $formation, EmailAnnouncements $announcements): JsonResponse
    {
        $viewer = $this->viewer($request, $formation);

        abort_unless(EmailAnnouncements::canUse($viewer->user), 403);

        $check = $announcements->check($viewer->group, $viewer->user);

        return response()->json([
            ...$check,
            'text' => $check['allowed']
                ? 'Cet e-mail sera envoyé à '.$check['recipients'].' '.($check['recipients'] > 1 ? 'membres' : 'membre').'.'
                : $check['reason'],
        ]);
    }

    /**
     * @param  array{status: string, recipients: int, reason: string|null}  $email
     */
    private static function emailStatus(array $email): string
    {
        return $email['status'] === 'queued'
            ? 'Annonce publiée. E-mail en cours d\'envoi à '.$email['recipients'].' '.($email['recipients'] > 1 ? 'membres' : 'membre').'.'
            : (string) $email['reason'];
    }

    /**
     * Supprimer : son propre message, ou n'importe quel message pour l'équipe.
     */
    public function destroy(Request $request, Formation $formation, ChatMessage $message): JsonResponse|RedirectResponse
    {
        $viewer = $this->viewer($request, $formation);
        $message = $this->messageOf($viewer, $message);

        abort_unless($viewer->canDelete($message), 403);

        $message->wipe($viewer->user);

        // Les signalements de ce message sont traités par la suppression.
        if ($viewer->isStaff()) {
            $message->reports()->where('status', 'open')->update(['status' => 'resolved', 'handled_by' => $viewer->user->id, 'handled_at' => now(), 'updated_at' => now()]);
        }

        $this->realtime->notify($viewer->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);

        return $this->done($request, $formation, 'Message supprimé.');
    }

    /**
     * Épingler ou désépingler (équipe uniquement).
     */
    public function togglePin(Request $request, Formation $formation, ChatMessage $message): JsonResponse|RedirectResponse
    {
        $viewer = $this->viewer($request, $formation);
        $message = $this->messageOf($viewer, $message);

        abort_unless($viewer->canPin($message), 403);

        $pins = app(MessagePins::class);

        if ($message->isPinned()) {
            $pins->unpin($message);
            $status = 'Message désépinglé.';
        } else {
            $pins->pin($message, $viewer->user);
            $status = 'Message épinglé en haut du groupe.';
        }

        return $this->done($request, $formation, $status);
    }

    /**
     * Réagir par emoji (un seul par personne : un autre emoji remplace, le même retire). Aucune notification.
     */
    public function react(Request $request, Formation $formation, ChatMessage $message, MessageReactions $reactions): JsonResponse|RedirectResponse
    {
        $viewer = $this->viewer($request, $formation);
        $message = $this->messageOf($viewer, $message);

        abort_unless($viewer->canReact($message), 403, $viewer->member->isMuted() && ! $viewer->isStaff() ? 'Vous êtes en sourdine : vous ne pouvez pas réagir pour le moment.' : 'Réaction impossible sur ce message.');

        $data = $request->validate(['emoji' => ['required', Rule::enum(ChatReaction::class)]], ['emoji.required' => 'Choisissez une réaction.', 'emoji.enum' => 'Réaction inconnue.']);

        $reaction = $reactions->toggle($message, $viewer->user, ChatReaction::from($data['emoji']));

        if (! $request->expectsJson()) {
            return redirect()->to(route('chat.show', $formation).'#m-'.$message->id);
        }

        $message->load(['author', 'replyTo.author', 'reactions']);

        return response()->json([
            'reaction' => $reaction?->value,
            'html' => view('site.chat.partials.message', [
                'message' => $message,
                'viewer' => $viewer,
                'grouped' => MessageGrouping::continues($message, app(ConversationLoader::class)->previousOf($viewer->group, $message->id)),
            ])->render(),
        ]);
    }

    /**
     * Signaler un message à l'équipe (une fois par message et par membre).
     */
    public function report(Request $request, Formation $formation, ChatMessage $message): JsonResponse|RedirectResponse
    {
        $viewer = $this->viewer($request, $formation);
        $message = $this->messageOf($viewer, $message);

        abort_unless($viewer->canReport($message), 403);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
        ], [
            'reason.required' => 'Choisissez la raison du signalement.',
            'details.max' => 'Précisions trop longues : 500 caractères au maximum.',
        ]);

        ChatMessageReport::query()->updateOrCreate(
            ['chat_message_id' => $message->id, 'user_id' => $viewer->user->id],
            ['reason' => $data['reason'], 'details' => filled($data['details'] ?? null) ? trim((string) $data['details']) : null, 'status' => 'open', 'handled_by' => null, 'handled_at' => null],
        );

        return $this->done($request, $formation, 'Merci, le message a été signalé à l\'équipe.');
    }

    private function done(Request $request, Formation $formation, string $status): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['status' => $status])
            : redirect()->route('chat.show', $formation)->with('status', $status);
    }
}
