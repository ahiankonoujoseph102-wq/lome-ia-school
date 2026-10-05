<?php

namespace App\Assistant;

use App\Contracts\SpeechSynthesizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Voix naturelle d'Ama : Cloudflare Workers AI, modèle MeloTTS (« @cf/myshell-ai/melotts »), en français, appelé
 * uniquement par le serveur avec CLOUDFLARE_ACCOUNT_ID et CLOUDFLARE_API_TOKEN (jamais dans le navigateur ni le dépôt).
 *
 * Vérifié le 5 octobre 2026 dans la source officielle de la documentation de Cloudflare (dépôt GitHub
 * cloudflare/cloudflare-docs : fiche du modèle « melotts.json », guide « REST API », pages « Pricing », « Limits » et
 * « Errors » ; le site developers.cloudflare.com est bloqué depuis l'environnement de Claude) :
 * - Adresse : POST https://api.cloudflare.com/client/v4/accounts/{ACCOUNT_ID}/ai/run/@cf/myshell-ai/melotts, clé dans
 *   l'en-tête « Authorization: Bearer … » (jeton avec les droits « Workers AI - Read » et « Workers AI - Edit »).
 * - Demande : {"prompt": texte (1 caractère au moins), "lang": "fr"} ; la langue vaut « en » si elle est absente. Aucune
 *   longueur maximale n'est donnée par la documentation, aucun choix de voix.
 * - Réponse : MP3, soit en JSON (« audio » en base64, dans « result » pour l'API REST), soit en binaire « audio/mpeg » :
 *   les deux formes sont acceptées ici.
 * - Erreurs : 429 code 3036 = offre gratuite du jour (10 000 neurones) épuisée ; 429 code 3040 = capacité dépassée
 *   momentanément ; 408 code 3007 = délai dépassé. Les limites recommencent à 0 h 00 UTC (0 h 00 à Lomé).
 *
 * Aucun nouvel essai : Ama parle aussitôt avec la voix du téléphone en cas d'échec. Le jeton et le numéro de compte
 * n'apparaissent jamais dans les messages d'erreur (remplacés par « [clé] » et « [compte] »).
 */
class CloudflareSpeech implements SpeechSynthesizer
{
    public const string MODEL = '@cf/myshell-ai/melotts';

    public const string LANG = 'fr';

    /**
     * Code d'erreur de Cloudflare : offre gratuite du jour épuisée.
     */
    public const int DAILY_ALLOCATION_ERROR = 3036;

    public function isConfigured(): bool
    {
        return $this->accountId() !== '' && $this->token() !== '';
    }

    /**
     * Audio MP3 du texte, en français. La voix et le modèle de Gemini ne s'appliquent pas ici.
     */
    public function synthesize(string $text, string $voice = '', string $model = ''): SpeechAudio
    {
        if (! $this->isConfigured()) {
            throw new SpeechFailure('Variables CLOUDFLARE_ACCOUNT_ID ou CLOUDFLARE_API_TOKEN absentes.');
        }

        $url = rtrim((string) config('services.cloudflare.endpoint'), '/').'/accounts/'.rawurlencode($this->accountId()).'/ai/run/'.self::MODEL;

        try {
            $response = Http::withToken($this->token())
                ->accept('application/json, audio/mpeg')
                ->connectTimeout(3)
                ->timeout(self::timeoutSeconds())
                ->post($url, ['prompt' => $text, 'lang' => self::LANG]);
        } catch (Throwable $exception) {
            throw new SpeechFailure($this->hide('Délai dépassé ou connexion impossible : '.$exception->getMessage()), timeout: true);
        }

        if ($response->failed()) {
            $status = $response->status();

            throw new SpeechFailure(
                $this->describe($response),
                quota: $status === 429,
                daily: $status === 429 && self::isDailyAllocation($response),
                timeout: $status === 408,
            );
        }

        $type = strtolower((string) $response->header('Content-Type'));

        if (str_starts_with($type, 'audio/')) {
            $bytes = $response->body();

            if ($bytes !== '') {
                return new SpeechAudio($bytes, 'audio/mpeg');
            }
        }

        $encoded = $response->json('result.audio') ?? $response->json('audio');
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;

        if (is_string($bytes) && $bytes !== '') {
            return new SpeechAudio($bytes, 'audio/mpeg');
        }

        throw new SpeechFailure('Réponse sans audio de Cloudflare.');
    }

    /**
     * Délai d'attente de Cloudflare côté serveur (secondes) : CLOUDFLARE_TTS_TIMEOUT (8 s par défaut), et toujours au
     * moins 2 secondes de plus que le délai du téléphone réglé dans l'admin (même règle que GeminiSpeech).
     */
    public static function timeoutSeconds(): int
    {
        return max(2, (int) config('services.cloudflare.tts_timeout', 8), (int) ceil(NaturalVoice::timeoutMs() / 1000) + 2);
    }

    /**
     * Le refus 429 vient-il de l'offre gratuite du jour épuisée (code 3036) ?
     */
    public static function isDailyAllocation(Response $response): bool
    {
        foreach ((array) $response->json('errors') as $error) {
            if (is_array($error) && (int) ($error['code'] ?? 0) === self::DAILY_ALLOCATION_ERROR) {
                return true;
            }
        }

        $text = strtolower($response->body());

        return str_contains($text, (string) self::DAILY_ALLOCATION_ERROR) || str_contains($text, 'daily free allocation');
    }

    private function describe(Response $response): string
    {
        $error = (array) ($response->json('errors.0') ?? []);
        $detail = trim(((string) ($error['code'] ?? '')).' '.mb_substr((string) ($error['message'] ?? ''), 0, 200));

        return $this->hide(trim('HTTP '.$response->status().' '.$detail));
    }

    private function accountId(): string
    {
        return trim((string) config('services.cloudflare.account_id'));
    }

    private function token(): string
    {
        return trim((string) config('services.cloudflare.api_token'));
    }

    private function hide(string $text): string
    {
        foreach (['[clé]' => $this->token(), '[compte]' => $this->accountId()] as $mask => $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, $mask, $text);
            }
        }

        return mb_substr($text, 0, 300);
    }
}
