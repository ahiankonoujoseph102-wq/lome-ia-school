<?php

namespace App\WhatsApp;

use App\Contracts\WhatsAppSender;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * API WhatsApp Business Cloud de Meta : POST https://graph.facebook.com/{version}/{identifiant du numéro}/messages.
 *
 * - Le jeton part dans l'en-tête « Authorization », jamais dans l'adresse ; il n'apparaît ni dans la base, ni dans
 *   les journaux, ni dans les erreurs montrées à l'admin (toute trace est remplacée par « [jeton] »).
 * - Réglages dans config/services.php (clé « whatsapp ») : WHATSAPP_ACCESS_TOKEN, WHATSAPP_PHONE_NUMBER_ID,
 *   WHATSAPP_API_VERSION (version de l'API Graph, modifiable sans toucher au code).
 */
class CloudApiSender implements WhatsAppSender
{
    /**
     * Longueur maximale d'un message texte chez WhatsApp.
     */
    public const int MAX_TEXT_LENGTH = 4096;

    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->phoneNumberId() !== '';
    }

    public function sendText(string $to, string $text): string
    {
        if (! $this->isConfigured()) {
            throw new WhatsAppSendFailed('Jeton WhatsApp ou identifiant du numéro absent.');
        }

        $text = mb_strlen($text) > self::MAX_TEXT_LENGTH ? mb_substr($text, 0, self::MAX_TEXT_LENGTH - 1).'…' : $text;

        $response = $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => preg_replace('/\D/', '', $to),
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $text],
        ]);

        $id = $response->json('messages.0.id');

        if ($response->failed() || ! is_string($id) || $id === '') {
            $code = $response->json('error.code');

            throw new WhatsAppSendFailed($this->describe($response), is_numeric($code) ? (int) $code : null);
        }

        return $id;
    }

    public function markAsRead(string $messageId): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        try {
            $this->post([
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
                'typing_indicator' => ['type' => 'text'],
            ]);
        } catch (Throwable) {
            // Simple confort pour le client : un échec ne bloque jamais la réponse.
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function post(array $body): Response
    {
        $url = rtrim((string) config('services.whatsapp.endpoint'), '/').'/'.trim((string) config('services.whatsapp.api_version'), '/')
            .'/'.rawurlencode($this->phoneNumberId()).'/messages';

        try {
            return Http::withToken($this->token())
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(max(5, (int) config('services.whatsapp.timeout', 10)))
                ->post($url, $body);
        } catch (Throwable $exception) {
            throw new WhatsAppSendFailed($this->hide('Meta injoignable : '.$exception->getMessage()));
        }
    }

    /**
     * Résumé d'une erreur de Meta pour l'admin : code HTTP, code d'erreur et début du message.
     */
    private function describe(Response $response): string
    {
        $code = (string) ($response->json('error.code') ?? '');
        $message = (string) ($response->json('error.error_data.details') ?? $response->json('error.message') ?? '');

        return $this->hide(trim('HTTP '.$response->status().($code !== '' ? ' (code '.$code.')' : '').' '.mb_substr($message, 0, 200)));
    }

    private function hide(string $text): string
    {
        $token = $this->token();

        return mb_substr($token !== '' ? str_replace($token, '[jeton]', $text) : $text, 0, 300);
    }

    private function token(): string
    {
        return trim((string) config('services.whatsapp.token'));
    }

    private function phoneNumberId(): string
    {
        return trim((string) config('services.whatsapp.phone_number_id'));
    }
}
