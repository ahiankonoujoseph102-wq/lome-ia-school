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
 * - Mode appel : réponse reçue en continu (méthode « streamGenerateContent », format « alt=sse »), transmise morceau
 *   par morceau pour qu'Ama commence à parler dès la première phrase.
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
        $response = $this->send($instructions, $messages, false);
        $text = self::clean($this->text($response));

        if ($text === '') {
            throw $this->emptyReply($response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason'));
        }

        return $text;
    }

    public function stream(string $instructions, array $messages, callable $onText): string
    {
        $response = $this->send($instructions, $messages, true);
        $body = $response->toPsrResponse()->getBody();
        $raw = '';
        $sent = '';
        $buffer = '';
        $reason = null;

        // Un événement « data: {…} » du format SSE de Google : texte ajouté, partie stable transmise.
        $handle = function (string $event) use (&$raw, &$sent, &$reason, $onText): void {
            $event = trim($event);

            if (! str_starts_with($event, 'data:')) {
                return;
            }

            $data = json_decode(trim(substr($event, 5)), true);

            if (! is_array($data)) {
                return;
            }

            $raw .= $this->partsText($data['candidates'][0]['content']['parts'] ?? null);
            $reason = $data['promptFeedback']['blockReason'] ?? $data['candidates'][0]['finishReason'] ?? $reason;
            $sent = $this->emitStable($raw, $sent, $onText);
        };

        try {
            while (! $body->eof() && mb_strlen($sent) < self::MAX_REPLY_LENGTH) {
                $buffer .= $body->read(1024);

                while (($position = strpos($buffer, "\n")) !== false) {
                    $handle(substr($buffer, 0, $position));
                    $buffer = substr($buffer, $position + 1);
                }
            }

            $handle($buffer);
        } catch (Throwable $exception) {
            throw new AssistantFailure(AssistantIncidentType::GeminiTimeout, $this->hide('Réponse en continu interrompue : '.$exception->getMessage()));
        }

        $text = self::clean($raw);

        if (self::spoken($text) === '') {
            throw $this->emptyReply($reason);
        }

        // Fin de la réponse (morceau gardé en attente d'un espace).
        $final = self::spoken($text);

        if (str_starts_with($final, $sent) && $final !== $sent) {
            $onText(substr($final, strlen($sent)));
        }

        return $text;
    }

    /**
     * @param  list<array{role: string, text: string}>  $messages
     */
    private function send(string $instructions, array $messages, bool $stream): Response
    {
        if (! $this->isConfigured()) {
            throw new AssistantFailure(AssistantIncidentType::MissingKey);
        }

        $model = trim((string) config('services.gemini.model'));
        $url = rtrim((string) config('services.gemini.endpoint'), '/').'/models/'.rawurlencode($model)
            .($stream ? ':streamGenerateContent?alt=sse' : ':generateContent');

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
                ->withOptions($stream ? ['stream' => true] : [])
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

        return $response;
    }

    private function emptyReply(mixed $reason): AssistantFailure
    {
        $model = trim((string) config('services.gemini.model'));

        return new AssistantFailure(AssistantIncidentType::EmptyReply, $this->hide('Modèle '.$model.' : '.(is_string($reason) && $reason !== '' ? $reason : 'aucun texte')));
    }

    private function key(): string
    {
        return trim((string) config('services.gemini.key'));
    }

    /**
     * Texte de la réponse (sans les parties de réflexion).
     */
    private function text(Response $response): string
    {
        return $this->partsText($response->json('candidates.0.content.parts'));
    }

    private function partsText(mixed $parts): string
    {
        $text = '';

        foreach (is_array($parts) ? $parts : [] as $part) {
            if (is_array($part) && ! ($part['thought'] ?? false) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    /**
     * Réponse sans mise en forme Markdown : elle est affichée telle quelle et peut être lue à voix haute.
     */
    public static function clean(string $text): string
    {
        $text = (string) preg_replace('/\*\*(.+?)\*\*/su', '$1', $text);
        $text = (string) preg_replace('/^\s{0,3}#{1,6}\s+/mu', '', $text);
        $text = (string) preg_replace('/^\s*[*\-]\s+/mu', '• ', $text);
        $text = str_replace(['`', '*'], '', $text);
        $text = trim((string) preg_replace("/\n{3,}/u", "\n\n", $text));

        return mb_strlen($text) > self::MAX_REPLY_LENGTH ? rtrim(mb_substr($text, 0, self::MAX_REPLY_LENGTH - 1)).'…' : $text;
    }

    /**
     * Texte montré et lu pendant la réponse en continu : mise en forme et repère [INCONNU] retirés.
     */
    private static function spoken(string $raw): string
    {
        $text = (string) preg_replace('/\s*\[\s*INCONNU\s*\]\s*/iu', ' ', self::clean($raw));

        return ltrim($text);
    }

    /**
     * Envoie la partie de la réponse qui ne peut plus changer (jusqu'au dernier espace ; rien tant qu'un repère
     * « [ » pourrait être en cours ou que le début est trop court pour savoir s'il commence par [INCONNU]).
     *
     * @param  callable(string): void  $onText
     */
    private function emitStable(string $raw, string $sent, callable $onText): string
    {
        $text = self::spoken($raw);

        if (mb_strlen(ltrim($raw)) < 12) {
            return $sent;
        }

        $open = strrpos($text, '[');
        $stable = $open !== false && ! str_contains(substr($text, $open), ']') ? substr($text, 0, $open) : $text;
        $space = max((int) strrpos($stable, ' '), (int) strrpos($stable, "\n"));
        $stable = $space > 0 ? substr($stable, 0, $space + 1) : '';

        if ($stable === '' || ! str_starts_with($stable, $sent) || strlen($stable) <= strlen($sent)) {
            return $sent;
        }

        $onText(substr($stable, strlen($sent)));

        return $stable;
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
