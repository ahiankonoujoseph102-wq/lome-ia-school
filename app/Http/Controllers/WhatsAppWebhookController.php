<?php

namespace App\Http\Controllers;

use App\WhatsApp\AgentSettings;
use App\WhatsApp\WebhookInbox;
use App\WhatsApp\WhatsAppAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use function Illuminate\Support\defer;

/**
 * Webhook de l'API WhatsApp Business Cloud (adresse à recopier dans l'écran « Webhook » de l'application Meta).
 *
 * - GET : vérification par Meta lors de l'enregistrement de l'adresse (hub.mode = subscribe, hub.verify_token = notre
 *   mot de contrôle WHATSAPP_VERIFY_TOKEN) : on renvoie hub.challenge.
 * - POST : messages reçus et suivi des envois. Refusé si la signature « X-Hub-Signature-256 » (HMAC SHA-256 du corps
 *   avec la clé secrète de l'application, WHATSAPP_APP_SECRET) est absente ou fausse : personne d'autre que Meta ne
 *   peut faire parler l'agent. Réponse 200 immédiate ; l'agent répond juste après (defer), jamais par la file
 *   d'attente (qui ne tourne qu'une fois par minute).
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = AgentSettings::verifyToken();
        $challenge = (string) $request->query('hub_challenge', '');

        if ($token === '' || $request->query('hub_mode') !== 'subscribe'
            || ! hash_equals($token, (string) $request->query('hub_verify_token', ''))
            || preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $challenge) !== 1) {
            return response('Refusé', 403)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function receive(Request $request, WebhookInbox $inbox): Response
    {
        if (! self::validSignature($request->getContent(), (string) $request->header('X-Hub-Signature-256', ''))) {
            return response('Signature invalide', 403);
        }

        $payload = json_decode($request->getContent(), true);
        $contacts = is_array($payload) ? $inbox->handle($payload) : [];

        if ($contacts !== []) {
            defer(fn () => app(WhatsAppAgent::class)->respondAll($contacts));
        }

        return response('OK', 200);
    }

    /**
     * Signature de Meta : « sha256= » suivi du HMAC SHA-256 du corps brut, avec la clé secrète de l'application.
     */
    public static function validSignature(string $body, string $header): bool
    {
        $secret = AgentSettings::appSecret();

        if ($secret === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), substr($header, 7));
    }
}
