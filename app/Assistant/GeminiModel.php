<?php

namespace App\Assistant;

use App\Contracts\AssistantModel;
use App\Enums\AssistantIncidentType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * API Gemini de Google (méthode « generateContent »), appelée uniquement par le serveur.
 *
 * - La clé part dans l'en-tête « x-goog-api-key », jamais dans l'adresse : elle n'apparaît ni dans la page,
 *   ni dans les journaux, ni dans les incidents (toute trace éventuelle est remplacée par « [clé] »).
 * - Modèle : GEMINI_MODEL (config services.gemini.model), modifiable sans toucher au code.
 * - Erreur passagère (500, 503) : un seul nouvel essai après 1 seconde, comme le conseille Google ; un quota
 *   atteint (429) n'est pas réessayé (il ne se libère qu'après une minute ou le lendemain).
 * - Réflexion (« thinking ») réduite au minimum pour répondre plus vite : niveau « minimal » pour les modèles Gemini 3
 *   et suivants (le plus bas possible : la réflexion ne peut pas y être coupée complètement), budget 0 pour les
 *   modèles 2.5 Flash. Réglage GEMINI_THINKING (« minimal » par défaut, vide = aucun réglage envoyé). Si Google refuse
 *   ce réglage pour le modèle choisi (erreur 400), la demande repart aussitôt sans lui, et il n'est plus envoyé pour
 *   ce modèle (mémorisé une journée).
 * - Mode appel : réponse reçue en continu (méthode « streamGenerateContent », format « alt=sse »), transmise morceau
 *   par morceau pour qu'Ama commence à parler dès la première phrase.
 */
class GeminiModel implements AssistantModel
{
    /**
     * Longueur maximale d'une réponse montrée au visiteur.
     */
    public const int MAX_REPLY_LENGTH = 1500;

    /**
     * Clé de cache : modèle qui a refusé le réglage de réflexion.
     */
    private const string NO_THINKING_CACHE = 'gemini:no-thinking:';

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

        $thinking = self::thinkingConfig($model);

        if ($thinking !== null) {
            $body['generationConfig'] = ['thinkingConfig' => $thinking];
        }

        $response = $this->post($url, $body, $stream);

        // Réglage de réflexion refusé pour ce modèle : la demande repart sans lui (une seule fois), et il n'est plus
        // envoyé pour ce modèle pendant une journée.
        if ($thinking !== null && $response->status() === 400 && str_contains(Str::lower((string) $response->json('error.message')), 'think')) {
            Cache::put(self::NO_THINKING_CACHE.$model, true, now()->addDay());
            unset($body['generationConfig']);
            $response = $this->post($url, $body, $stream);
        }

        if ($response->failed()) {
            throw new AssistantFailure(
                $response->status() === 429 ? AssistantIncidentType::GeminiQuota : AssistantIncidentType::GeminiError,
                $this->describe($response),
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function post(string $url, array $body, bool $stream): Response
    {
        try {
            return Http::withHeaders(['x-goog-api-key' => $this->key()])
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
    }

    /**
     * Réglage de réflexion envoyé à Google pour ce modèle (null : aucun).
     *
     * @return array<string, int|string>|null
     */
    public static function thinkingConfig(string $model): ?array
    {
        $level = Str::lower(trim((string) config('services.gemini.thinking', 'minimal')));

        if ($level === '' || Cache::get(self::NO_THINKING_CACHE.$model) === true) {
            return null;
        }

        // Gemini 2.5 : budget de réflexion en jetons (0 = aucune réflexion, accepté par les modèles Flash seulement).
        if (str_starts_with($model, 'gemini-2.5')) {
            return str_contains($model, 'flash') && $level === 'minimal' ? ['thinkingBudget' => 0] : null;
        }

        // Gemini 3 et suivants : niveau (« minimal » est le plus bas : la réflexion ne peut pas être coupée).
        return ['thinkingLevel' => $level];
    }

    /**
     * Réglage de réflexion en clair (mesure des durées, écran de l'admin).
     */
    public static function thinkingLabel(): string
    {
        $config = self::thinkingConfig(trim((string) config('services.gemini.model')));

        return match (true) {
            $config === null => 'aucun réglage envoyé',
            isset($config['thinkingBudget']) => 'budget '.$config['thinkingBudget'],
            default => 'niveau '.$config['thinkingLevel'],
        };
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

        // Ligne technique « [TRANSMETTRE] {…} » (carte de confirmation, App\Assistant\FounderHandoff) : gardée entière,
        // seule la réponse visible est raccourcie si besoin.
        $marker = '';

        if (preg_match('/\[\s*TRANSMETTRE\b/iu', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            $marker = "\n".substr($text, (int) $match[0][1]);
            $text = rtrim(substr($text, 0, (int) $match[0][1]));
        }

        $text = mb_strlen($text) > self::MAX_REPLY_LENGTH ? rtrim(mb_substr($text, 0, self::MAX_REPLY_LENGTH - 1)).'…' : $text;

        return trim($text.$marker);
    }

    /**
     * Texte montré et lu pendant la réponse en continu : mise en forme, repère [INCONNU] et ligne [TRANSMETTRE] retirés.
     */
    private static function spoken(string $raw): string
    {
        $text = (string) preg_replace('/\s*\[\s*INCONNU\s*\]\s*/iu', ' ', self::clean($raw));
        // La ligne technique « [TRANSMETTRE] {…} » et tout ce qui la suit ne sont jamais lus à voix haute.
        $text = (string) preg_replace('/\s*\[\s*TRANSMETTRE\b.*$/isu', '', $text);

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
