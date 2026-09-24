<?php

namespace App\Events\Chat;

use App\Models\ChatGroup;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Signal envoyé en temps réel (Pusher) aux membres d'un groupe : « il y a du nouveau ».
 *
 * Diffusé immédiatement, sans file d'attente. Il ne contient aucun contenu de message :
 * chaque navigateur recharge les nouveautés avec ses propres droits (même chemin que la mise à jour régulière).
 */
class ChatGroupChanged implements ShouldBroadcastNow
{
    public const string MESSAGE_CREATED = 'message.created';

    public const string MESSAGE_UPDATED = 'message.updated';

    public const string GROUP_UPDATED = 'group.updated';

    public function __construct(
        public ChatGroup $group,
        public string $kind,
        public ?int $messageId = null,
    ) {}

    public static function channelName(ChatGroup $group): string
    {
        return 'chat.group.'.$group->id;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->group))];
    }

    public function broadcastAs(): string
    {
        return 'chat.changed';
    }

    /**
     * @return array{kind: string, message_id: int|null}
     */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'message_id' => $this->messageId];
    }
}
