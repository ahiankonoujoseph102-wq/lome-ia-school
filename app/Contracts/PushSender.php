<?php

namespace App\Contracts;

use App\Models\PushSubscription;

/**
 * Envoi d'une notification sur téléphone (Web Push). Choisi par la configuration :
 * App\Push\WebPushSender quand les clés VAPID sont dans .env, App\Push\NullPushSender sinon.
 */
interface PushSender
{
    public function isConfigured(): bool;

    /**
     * @param  array{title: string, body: string, url: string, tag?: string}  $payload
     * @return bool false si l'abonnement n'existe plus (appareil désabonné) : il faut alors le supprimer.
     */
    public function send(PushSubscription $subscription, array $payload): bool;
}
