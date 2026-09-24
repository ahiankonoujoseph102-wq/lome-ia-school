<?php

namespace Tests\Fakes;

use App\Contracts\PushSender;
use App\Models\PushSubscription;

/**
 * Faux envoi de notifications sur téléphone : garde les envois en mémoire.
 */
class FakePushSender implements PushSender
{
    /** @var list<array{user_id: int, payload: array<string, string>}> */
    public array $sent = [];

    /** @var list<string> Adresses d'envoi à considérer comme expirées. */
    public array $expired = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(PushSubscription $subscription, array $payload): bool
    {
        if (in_array($subscription->endpoint, $this->expired, true)) {
            return false;
        }

        $this->sent[] = ['user_id' => $subscription->user_id, 'payload' => $payload];

        return true;
    }

    /**
     * @return list<int>
     */
    public function recipients(): array
    {
        return array_values(array_unique(array_column($this->sent, 'user_id')));
    }
}
