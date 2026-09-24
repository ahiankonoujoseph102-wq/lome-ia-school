<?php

namespace App\Jobs;

use App\Contracts\PushSender;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Notifications sur téléphone d'un nouveau message (file d'attente traitée par le cron).
 *
 * Destinataires : membres actifs du groupe, sauf l'auteur, ceux qui ont coupé les notifications
 * (partout ou pour ce groupe), ceux qui ont déjà lu le message et ceux qui ont le groupe ouvert à l'écran.
 */
class SendChatPushNotifications implements ShouldQueue
{
    use Queueable;

    /**
     * Un membre actif dans le groupe depuis moins de ce délai (en secondes) le voit déjà à l'écran.
     */
    public const int ACTIVE_WINDOW = 20;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(PushSender $sender): void
    {
        if (! $sender->isConfigured()) {
            return;
        }

        $message = ChatMessage::query()->with(['group.formation', 'author'])->find($this->messageId);

        if ($message === null || $message->isDeleted()) {
            return;
        }

        $payload = [
            'title' => $message->group->formation->title,
            'body' => $message->author->first_name.' : '.$message->preview(120),
            'url' => route('chat.show', $message->group->formation),
            'tag' => 'chat-group-'.$message->chat_group_id,
        ];

        ChatGroupMember::query()
            ->where('chat_group_id', $message->chat_group_id)
            ->whereNull('removed_at')
            ->where('user_id', '!=', $message->user_id)
            ->where('notifications_muted', false)
            ->where(fn ($query) => $query->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $message->id))
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subSeconds(self::ACTIVE_WINDOW)))
            ->whereHas('user', fn ($query) => $query->where('chat_push_enabled', true)->whereNull('blocked_at'))
            ->pluck('user_id')
            ->chunk(200)
            ->each(function ($userIds) use ($sender, $payload): void {
                PushSubscription::query()->whereIn('user_id', $userIds)->get()->each(function (PushSubscription $subscription) use ($sender, $payload): void {
                    if ($sender->send($subscription, $payload)) {
                        $subscription->forceFill(['last_used_at' => now()])->save();
                    } else {
                        $subscription->delete();
                    }
                });
            });
    }
}
