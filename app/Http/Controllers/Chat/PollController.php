<?php

namespace App\Http\Controllers\Chat;

use App\Chat\ChatFeatures;
use App\Chat\ChatPolls;
use App\Chat\ChatViewer;
use App\Chat\ConversationLoader;
use App\Chat\MessageGrouping;
use App\Enums\ChatMessageType;
use App\Http\Controllers\Chat\Concerns\ResolvesChatMember;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\Formation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sondages des groupes (interrupteur « Sondages ») : création (administrateurs), vote et clôture.
 * Le membre doit faire partie du groupe (ResolvesChatMember) et le sondage appartenir à ce groupe.
 */
class PollController extends Controller
{
    use ResolvesChatMember;

    public function store(Request $request, Formation $formation, ChatPolls $polls): JsonResponse
    {
        abort_unless(ChatFeatures::polls(), 404);

        $viewer = $this->viewer($request, $formation);

        abort_unless(ChatPolls::canCreate($viewer->user), 403, 'Seuls les administrateurs peuvent créer un sondage.');

        $request->merge([
            'question' => Str::squish($request->string('question')->toString()),
            'options' => collect((array) $request->input('options', []))
                ->map(fn (mixed $option): string => Str::squish(is_string($option) ? $option : ''))
                ->filter(fn (string $option): bool => $option !== '')
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:'.ChatPoll::QUESTION_MAX_LENGTH],
            'options' => ['required', 'array', 'min:'.ChatPoll::MIN_OPTIONS, 'max:'.ChatPoll::MAX_OPTIONS],
            'options.*' => ['required', 'string', 'max:'.ChatPoll::OPTION_MAX_LENGTH],
            'multiple' => ['nullable', 'boolean'],
            'reply_to_id' => ['nullable', 'integer'],
        ], [
            'question.required' => 'Écrivez la question du sondage.',
            'question.max' => 'Question trop longue : '.ChatPoll::QUESTION_MAX_LENGTH.' caractères au maximum.',
            'options.required' => 'Ajoutez au moins '.ChatPoll::MIN_OPTIONS.' choix.',
            'options.min' => 'Ajoutez au moins '.ChatPoll::MIN_OPTIONS.' choix.',
            'options.max' => ChatPoll::MAX_OPTIONS.' choix au maximum.',
            'options.*.max' => 'Un choix est trop long : '.ChatPoll::OPTION_MAX_LENGTH.' caractères au maximum.',
        ]);

        $options = array_values($data['options']);
        $normalized = array_map(fn (string $option): string => Str::lower(Str::ascii($option)), $options);

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw ValidationException::withMessages(['options' => 'Deux choix sont identiques : modifiez-en un.']);
        }

        $message = $polls->create($viewer, $data['question'], $options, (bool) ($data['multiple'] ?? false), isset($data['reply_to_id']) ? (int) $data['reply_to_id'] : null);

        return response()->json(['id' => $message->id], 201);
    }

    /**
     * Voter, modifier ou retirer son vote : la liste envoyée remplace le vote (liste vide : vote retiré).
     */
    public function vote(Request $request, Formation $formation, ChatPoll $poll, ChatPolls $polls): JsonResponse
    {
        abort_unless(ChatFeatures::polls(), 404);

        [$viewer, $message] = $this->pollOf($request, $formation, $poll);

        if (! ChatPolls::canVote($poll, $message)) {
            throw ValidationException::withMessages(['options' => 'Ce sondage est clôturé : il n\'accepte plus de vote.']);
        }

        $data = $request->validate([
            'options' => ['present', 'array', 'max:'.ChatPoll::MAX_OPTIONS],
            'options.*' => ['integer'],
        ], ['options.present' => 'Choisissez une réponse.']);

        $polls->vote($poll, $viewer->user, array_values(array_map('intval', (array) $data['options'])));

        return $this->rendered($viewer, $message);
    }

    public function close(Request $request, Formation $formation, ChatPoll $poll, ChatPolls $polls): JsonResponse
    {
        abort_unless(ChatFeatures::polls(), 404);

        [$viewer, $message] = $this->pollOf($request, $formation, $poll);

        abort_unless(ChatPolls::canClose($poll, $message, $viewer->user), 403, 'Seuls l\'auteur du sondage et les administrateurs peuvent le clôturer.');

        $polls->close($poll, $viewer->user);

        return $this->rendered($viewer, $message, 'Sondage clôturé.');
    }

    /**
     * Membre du groupe (sinon 403) et sondage de ce groupe (sinon 404).
     *
     * @return array{0: ChatViewer, 1: ChatMessage}
     */
    private function pollOf(Request $request, Formation $formation, ChatPoll $poll): array
    {
        $viewer = $this->viewer($request, $formation);
        $message = $poll->message;

        abort_unless($message->type === ChatMessageType::Poll, 404);
        $message = $this->messageOf($viewer, $message);
        $poll->setRelation('message', $message);

        return [$viewer, $message];
    }

    private function rendered(ChatViewer $viewer, ChatMessage $message, ?string $status = null): JsonResponse
    {
        $message = $message->fresh(['author', 'replyTo.author', 'reactions']) ?? $message;
        $message->setRelation('group', $viewer->group);

        return response()->json([
            'status' => $status,
            'html' => view('site.chat.partials.message', [
                'message' => $message,
                'viewer' => $viewer,
                'grouped' => MessageGrouping::continues($message, app(ConversationLoader::class)->previousOf($viewer->group, $message->id)),
            ])->render(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
