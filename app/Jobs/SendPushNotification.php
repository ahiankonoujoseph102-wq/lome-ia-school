<?php

namespace App\Jobs;

use App\Contracts\PushSender;
use App\Models\PushSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Notification sur téléphone envoyée à une liste d'élèves (file d'attente traitée par le cron).
 * Seuls les élèves qui ont gardé « Notifications sur le téléphone » activées la reçoivent.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  list<int>  $userIds
     * @param  array{title: string, body: string, url: string, tag?: string}  $payload
     */
    public function __construct(public array $userIds, public array $payload) {}

    public function handle(PushSender $sender): void
    {
        if (! $sender->isConfigured() || $this->userIds === []) {
            return;
        }

        PushSubscription::query()
            ->whereIn('user_id', $this->userIds)
            ->whereHas('user', fn ($query) => $query->where('chat_push_enabled', true)->whereNull('blocked_at'))
            ->get()
            ->each(function (PushSubscription $subscription) use ($sender): void {
                if ($sender->send($subscription, $this->payload)) {
                    $subscription->forceFill(['last_used_at' => now()])->save();
                } else {
                    $subscription->delete();
                }
            });
    }
}
