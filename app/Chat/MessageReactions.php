<?php

namespace App\Chat;

use App\Enums\ChatReaction;
use App\Events\Chat\ChatGroupChanged;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Réactions par emoji : une seule par personne et par message. Toucher un autre emoji remplace la réaction,
 * toucher le même la retire. Aucune notification (ni téléphone, ni e-mail, ni non-lu) : seul l'affichage
 * du message est mis à jour chez les membres (temps réel ou mise à jour régulière).
 */
class MessageReactions
{
    public function __construct(private ChatRealtime $realtime) {}

    /**
     * Réaction de la personne après le toucher (null : retirée).
     */
    public function toggle(ChatMessage $message, User $user, ChatReaction $reaction): ?ChatReaction
    {
        $result = DB::transaction(function () use ($message, $user, $reaction): ?ChatReaction {
            $existing = ChatMessageReaction::query()
                ->where('chat_message_id', $message->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->emoji === $reaction) {
                $existing->delete();

                return null;
            }

            if ($existing !== null) {
                $existing->update(['emoji' => $reaction]);

                return $reaction;
            }

            try {
                ChatMessageReaction::query()->create(['chat_message_id' => $message->id, 'user_id' => $user->id, 'emoji' => $reaction]);
            } catch (UniqueConstraintViolationException) {
                // Deux touchers presque simultanés : la ligne existe déjà, on la met à jour.
                ChatMessageReaction::query()->where('chat_message_id', $message->id)->where('user_id', $user->id)->update(['emoji' => $reaction->value, 'updated_at' => now()]);
            }

            return $reaction;
        });

        // Date de modification du message avancée : les autres membres reçoivent le nouvel affichage
        // par la mise à jour régulière (messages modifiés depuis la dernière mise à jour).
        $message->touch();
        $message->unsetRelation('reactions');
        $this->realtime->notify($message->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);

        return $result;
    }
}
