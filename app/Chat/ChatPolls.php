<?php

namespace App\Chat;

use App\Enums\ChatMessageType;
use App\Events\Chat\ChatGroupChanged;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sondages des groupes (interrupteur « Sondages ») : création par un administrateur (dont le formateur principal),
 * vote, modification ou retrait du vote par tous les membres, clôture par l'auteur ou un administrateur.
 * Comme les réactions, un vote ne déclenche aucune notification : seul l'affichage du message est mis à jour.
 */
class ChatPolls
{
    public function __construct(
        private PostMessage $post,
        private ChatRealtime $realtime,
    ) {}

    /**
     * Bouton « Sondage » : administrateurs avec double authentification (jamais les modérateurs ni les élèves).
     */
    public static function canCreate(User $user): bool
    {
        return ChatFeatures::polls() && $user->isAdmin() && $user->hasEnabledTwoFactorAuthentication();
    }

    public static function canVote(ChatPoll $poll, ChatMessage $message): bool
    {
        return ChatFeatures::polls() && ! $poll->isClosed() && ! $message->isDeleted();
    }

    public static function canClose(ChatPoll $poll, ChatMessage $message, User $user): bool
    {
        return ChatFeatures::polls() && ! $poll->isClosed() && ! $message->isDeleted()
            && ($message->user_id === $user->id || $user->isAdmin());
    }

    /**
     * @param  list<string>  $options
     */
    public function create(ChatViewer $viewer, string $question, array $options, bool $multiple, ?int $replyToId = null): ChatMessage
    {
        return $this->post->handle(
            $viewer->group,
            $viewer->member,
            $viewer->user,
            ChatMessageType::Poll,
            // Question gardée dans le message : aperçu des notifications et de la liste des groupes.
            $question,
            replyToId: $replyToId,
            details: function (ChatMessage $message) use ($question, $options, $multiple): void {
                $poll = ChatPoll::query()->create(['chat_message_id' => $message->id, 'question' => $question, 'allows_multiple' => $multiple]);

                foreach ($options as $position => $label) {
                    $poll->options()->create(['label' => $label, 'position' => $position]);
                }
            },
        );
    }

    /**
     * Remplace le vote de la personne par ces choix (aucun choix : vote retiré).
     *
     * @param  list<int>  $optionIds
     */
    public function vote(ChatPoll $poll, User $user, array $optionIds): void
    {
        DB::transaction(function () use ($poll, $user, $optionIds): void {
            /** @var ChatPoll $locked */
            $locked = ChatPoll::query()->whereKey($poll->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                throw ValidationException::withMessages(['options' => 'Ce sondage est clôturé : il n\'accepte plus de vote.']);
            }

            $valid = $locked->options()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $chosen = array_values(array_unique(array_map('intval', $optionIds)));

            if (array_diff($chosen, $valid) !== []) {
                throw ValidationException::withMessages(['options' => 'Choix inconnu pour ce sondage.']);
            }

            if (! $locked->allows_multiple && count($chosen) > 1) {
                throw ValidationException::withMessages(['options' => 'Un seul choix possible pour ce sondage.']);
            }

            $locked->votes()->where('user_id', $user->id)->delete();

            foreach ($chosen as $optionId) {
                $locked->votes()->create(['chat_poll_option_id' => $optionId, 'user_id' => $user->id]);
            }
        });

        $this->changed($poll);
    }

    public function close(ChatPoll $poll, User $by): void
    {
        $poll->forceFill(['closed_at' => now(), 'closed_by' => $by->id])->save();
        $this->changed($poll);
    }

    /**
     * Date de modification du message avancée : les autres membres reçoivent les nouveaux résultats
     * (temps réel ou mise à jour régulière), sans notification.
     */
    private function changed(ChatPoll $poll): void
    {
        $message = $poll->message;
        $message->touch();
        $this->realtime->notify($message->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);
    }
}
