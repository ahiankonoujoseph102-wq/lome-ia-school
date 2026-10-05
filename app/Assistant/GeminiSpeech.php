<?php

namespace App\Assistant;

use App\Contracts\SpeechSynthesizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Voix naturelle d'Ama : modèles « TTS » de Gemini (méthode « generateContent », réponse en audio), appelés
 * uniquement par le serveur avec la clé GEMINI_API_KEY déjà en place.
 *
 * - Demande : seulement le texte à dire (aucune consigne ajoutée, aucune donnée du visiteur), avec
 *   « responseModalities: ["AUDIO"] » et la voix préenregistrée choisie (« prebuiltVoiceConfig.voiceName », forme
 *   acceptée par les modèles 2.5, 3.1 et 3.8 ; la langue est reconnue d'après le texte).
 * - Réponse : audio en base64 dans « inlineData ». Les modèles 3.8 renvoient un fichier WAV complet ; les plus anciens
 *   un son brut (PCM 16 bits, mono, 24 000 Hz par défaut), complété ici par un en-tête WAV.
 * - Clé dans l'en-tête « x-goog-api-key », jamais dans l'adresse ni dans les traces (remplacée par « [clé] »).
 * - Aucun nouvel essai : Ama parle aussitôt avec la voix du téléphone en cas d'échec.
 */
class GeminiSpeech implements SpeechSynthesizer
{
    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    public function synthesize(string $text, string $voice, string $model): SpeechAudio
    {
        if (! $this->isConfigured()) {
            throw new SpeechFailure('Clé GEMINI_API_KEY absente.');
        }

        $url = rtrim((string) config('services.gemini.endpoint'), '/').'/models/'.rawurlencode($model).':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->key()])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(max(2, (int) config('services.gemini.tts_timeout', 8)))
                ->post($url, [
                    'contents' => [['parts' => [['text' => $text]]]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]]],
                    ],
                ]);
        } catch (Throwable $exception) {
            throw new SpeechFailure($this->hide('Délai dépassé ou connexion impossible : '.$exception->getMessage()));
        }

        if ($response->failed()) {
            throw new SpeechFailure($this->describe($response), $response->status() === 429, $response->status() === 429 && self::isDailyQuota($response));
        }

        foreach ((array) $response->json('candidates.0.content.parts') as $part) {
            $inline = is_array($part) ? ($part['inlineData'] ?? $part['inline_data'] ?? null) : null;

            if (is_array($inline) && is_string($inline['data'] ?? null)) {
                $bytes = base64_decode($inline['data'], true);

                if (is_string($bytes) && $bytes !== '') {
                    return self::audio($bytes, (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? ''));
                }
            }
        }

        throw new SpeechFailure('Réponse sans audio ('.((string) ($response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason') ?? 'aucune raison donnée')).').');
    }

    /**
     * Audio prêt à être joué par le navigateur : WAV gardé tel quel, son brut (PCM) complété par un en-tête WAV.
     */
    public static function audio(string $bytes, string $mime): SpeechAudio
    {
        $mime = Str::lower(trim($mime));

        if (str_starts_with($bytes, 'RIFF') || str_contains($mime, 'wav')) {
            return new SpeechAudio($bytes, 'audio/wav');
        }

        if (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
            return new SpeechAudio($bytes, 'audio/mpeg');
        }

        if (str_contains($mime, 'ogg') || str_contains($mime, 'opus')) {
            return new SpeechAudio($bytes, 'audio/ogg');
        }

        // « audio/L16;codec=pcm;rate=24000 » : 16 bits, mono, fréquence donnée par Google (24 000 Hz par défaut).
        $rate = preg_match('/rate=(\d+)/', $mime, $match) === 1 ? (int) $match[1] : 24000;

        return new SpeechAudio(self::wav($bytes, $rate), 'audio/wav');
    }

    /**
     * En-tête WAV (RIFF) d'un son brut 16 bits mono.
     */
    public static function wav(string $pcm, int $rate = 24000, int $channels = 1, int $bits = 16): string
    {
        $blockAlign = $channels * intdiv($bits, 8);

        return 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, $channels, $rate, $rate * $blockAlign, $blockAlign, $bits)
            .'data'.pack('V', strlen($pcm)).$pcm;
    }

    /**
     * Le refus 429 concerne-t-il le quota du jour (et non celui de la minute) ? Google l'indique dans les détails de
     * l'erreur (« quotaId » du type « …PerDay… ») ou dans son message.
     */
    public static function isDailyQuota(Response $response): bool
    {
        $text = Str::lower(json_encode($response->json('error.details') ?? []).' '.(string) $response->json('error.message'));

        return str_contains($text, 'perday') || str_contains($text, 'per day') || str_contains($text, 'per_day') || str_contains($text, 'daily');
    }

    private function describe(Response $response): string
    {
        $status = (string) ($response->json('error.status') ?? '');
        $message = (string) ($response->json('error.message') ?? '');

        return $this->hide(trim('HTTP '.$response->status().' '.$status.' '.mb_substr($message, 0, 200)));
    }

    private function key(): string
    {
        return trim((string) config('services.gemini.key'));
    }

    private function hide(string $text): string
    {
        $key = $this->key();

        return mb_substr($key !== '' ? str_replace($key, '[clé]', $text) : $text, 0, 300);
    }
}
