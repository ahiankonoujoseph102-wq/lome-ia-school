<?php

namespace App\Push;

use App\Contracts\PushSender;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Notifications sur téléphone via le protocole Web Push (clés VAPID dans .env).
 */
class WebPushSender implements PushSender
{
    private ?WebPush $client = null;

    /**
     * @param  array{public_key: string|null, private_key: string|null, subject: string|null}  $config
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return filled($this->config['public_key']) && filled($this->config['private_key']);
    }

    public function send(PushSubscription $subscription, array $payload): bool
    {
        try {
            $report = $this->client()->sendOneNotification(
                new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, $subscription->content_encoding),
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ['TTL' => 86400, 'urgency' => 'normal', 'topic' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $payload['tag'] ?? 'chat') ?: 'chat', 0, 32)],
            );
        } catch (Throwable $exception) {
            Log::warning('Notification sur téléphone non envoyée.', ['error' => $exception->getMessage()]);

            return true;
        }

        if ($report->isSubscriptionExpired()) {
            return false;
        }

        if (! $report->isSuccess()) {
            Log::info('Notification sur téléphone refusée par le service.', ['reason' => $report->getReason()]);
        }

        return true;
    }

    private function client(): WebPush
    {
        return $this->client ??= new WebPush([
            'VAPID' => [
                'subject' => $this->config['subject'] ?: (string) config('app.url'),
                'publicKey' => (string) $this->config['public_key'],
                'privateKey' => (string) $this->config['private_key'],
            ],
        ], ['TTL' => 86400]);
    }
}
