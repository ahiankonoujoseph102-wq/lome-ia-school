<?php

namespace App\Push;

use App\Contracts\PushSender;
use App\Models\PushSubscription;

/**
 * Clés Web Push absentes de .env : aucune notification envoyée, sans erreur.
 */
class NullPushSender implements PushSender
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function send(PushSubscription $subscription, array $payload): bool
    {
        return true;
    }
}
