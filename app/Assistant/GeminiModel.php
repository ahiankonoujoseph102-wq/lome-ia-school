<?php

namespace App\Assistant;

use App\Contracts\AssistantModel;
use App\Enums\AssistantIncidentType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * API Gemini de Google (méthode « generateContent »), appelée uniquement par le serveur.
 *
 * - La clé part dans l'en-tête « x-goog-api-key », jamais dans l'adresse : elle n'apparaît ni dans la page,
 *   ni dans les journaux, ni dans les incidents (toute trace éventuelle est remplacée par « [clé] »).
 * - Modèle : GEMINI_MODEL (config services.gemini.model), modifiable sans toucher au code.
 * - Erreur passagère (500, 503) : un seul nouvel essai après 1 seconde, comme le conseille Google ; un quota
 *   atteint (429) n'est pas réessayé (il ne se libère qu'après une minute ou le lendemain).
 */
class GeminiModel implements AssistantModel
{
    /**
     * Longueur maximale d'une réponse montrée au visiteur.
     */
    public const int MAX_REPLY_LENGTH = 1500;

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    public function reply(string $instructions, array $messages): string
    {
        if (! $this->isConfigured()) {
            throw new AssistantFailure(AssistantIncidentType::MissingKey);
        }

        $model = trim((string) config('services.gemini.model'));
        $url = rtrim((string) config('services.gemini.endpoint'), '/').'/models/'.rawurlencode($model).':generateContent';

        $body = [
            'system_instruction' => ['parts' => [['text' => $instructions]]],
            'contents' => array_map(fn (array $message): array => [
                'role' => $message['role'] === 'model' ? 'model' : 'user',
                'parts' => [['text' => $message['text']]],
            ], $messages),
        ];

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->key()])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(max(5, (int) config('services.gemini.timeout', 20)))
                ->retry(2, 1000, fn (Throwable $exception): bool => $exception instanceof RequestException && in_array($exception->response->status(), [500, 503], true), throw: false)
                ->post($url, $body);
        } catch (ConnectionException $exception) {
            throw new AssistantFailure(AssistantIncidentType::GeminiTimeout, $this->hide($exception->getMessage()));
        } catch (Throwable $exception) {
            throw new AssistantFailure(AssistantIncidentType::GeminiError, $this->hide($exception::class.' : '.$exception->getMessage()));
        }

        if ($response->failed()) {
            throw new AssistantFailure(
                $response->status() === 429 ? AssistantIncidentType::GeminiQuota : AssistantIncidentType::GeminiError,
                $this->describe($response),
            );
        }

        $text = $this->text($response);

        if ($text === '') {
            $reason = (string) ($response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason') ?? 'aucun texte');

            throw new AssistantFailure(AssistantIncidentType::EmptyReply, $this->hide('Modèle '.$model.' : '.$reason));
        }

        return $text;
    }

    private function key(): string
    {
        return trim((string) config('services.gemini.key'));
    }

    /**
     * Texte de la réponse (sans les parties de réflexion), sans mise en forme Markdown : il est affiché
     * tel quel et peut être lu à voix haute.
     */
    private function text(Response $response): string
    {
        $parts = $response->json('candidates.0.content.parts');
        $text = '';

        foreach (is_array($parts) ? $parts : [] as $part) {
            if (is_array($part) && ! ($part['thought'] ?? false) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        $text = (string) preg_replace('/\*\*(.+?)\*\*/su', '$1', $text);
        $text = (string) preg_replace('/^\s{0,3}#{1,6}\s+/mu', '', $text);
        $text = (string) preg_replace('/^\s*[*\-]\s+/mu', '• ', $text);
        $text = str_replace(['`', '*'], '', $text);
        $text = trim((string) preg_replace("/\n{3,}/u", "\n\n", $text));

        return mb_strlen($text) > self::MAX_REPLY_LENGTH ? rtrim(mb_substr($text, 0, self::MAX_REPLY_LENGTH - 1)).'…' : $text;
    }

    /**
     * Résumé d'une erreur de Google pour l'admin : code HTTP, statut et début du message.
     */
    private function describe(Response $response): string
    {
        $status = (string) ($response->json('error.status') ?? '');
        $message = (string) ($response->json('error.message') ?? '');

        return $this->hide(trim('HTTP '.$response->status().' '.$status.' '.mb_substr($message, 0, 200)));
    }

    /**
     * Retire toute trace de la clé d'un texte (par précaution).
     */
    private function hide(string $text): string
    {
        $key = $this->key();

        return mb_substr($key !== '' ? str_replace($key, '[clé]', $text) : $text, 0, 300);
    }
}
