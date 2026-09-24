<?php

namespace App\Http\Controllers\Chat;

use App\Chat\ChatRealtime;
use App\Chat\PostMessage;
use App\Enums\ChatMessageType;
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

        if (! $request->expectsJson()) {
            return redirect()->route('chat.show', $formation);
        }

        return response()->json(['id' => $message->id], 201);
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

        if ($message->isPinned()) {
            $message->forceFill(['pinned_at' => null, 'pinned_by' => null])->save();
            $status = 'Message désépinglé.';
        } else {
            // Au-delà du maximum, le plus ancien épinglé est retiré.
            $max = (int) config('chat.max_pinned');
            $viewer->group->pinnedMessages()->skip($max - 1)->take(PHP_INT_MAX)->get()
                ->each(fn (ChatMessage $old) => $old->forceFill(['pinned_at' => null, 'pinned_by' => null])->save());

            $message->forceFill(['pinned_at' => now(), 'pinned_by' => $viewer->user->id])->save();
            $status = 'Message épinglé en haut du groupe.';
        }

        $this->realtime->notify($viewer->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);

        return $this->done($request, $formation, $status);
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
