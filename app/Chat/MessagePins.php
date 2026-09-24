<?php

namespace App\Chat;

use App\Events\Chat\ChatGroupChanged;
use App\Models\ChatMessage;
use App\Models\User;

/**
 * Épingler / désépingler un message en haut du groupe (au-delà du maximum, le plus ancien est retiré).
 */
class MessagePins
{
    public function __construct(private ChatRealtime $realtime) {}

    public function pin(ChatMessage $message, User $by): void
    {
        $max = (int) config('chat.max_pinned');
        $message->group->pinnedMessages()->whereKeyNot($message->id)->skip($max - 1)->take(PHP_INT_MAX)->get()
            ->each(fn (ChatMessage $old) => $old->forceFill(['pinned_at' => null, 'pinned_by' => null])->save());

        $message->forceFill(['pinned_at' => now(), 'pinned_by' => $by->id])->save();
        $this->realtime->notify($message->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);
    }

    public function unpin(ChatMessage $message): void
    {
        if ($message->pinned_at === null) {
            return;
        }

        $message->forceFill(['pinned_at' => null, 'pinned_by' => null])->save();
        $this->realtime->notify($message->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);
    }
}
