<?php

namespace App\Assistant;

/**
 * Audio fabriqué pour la voix naturelle d'Ama : contenu du fichier et type (audio/wav le plus souvent).
 */
final readonly class SpeechAudio
{
    public function __construct(public string $bytes, public string $mime = 'audio/wav') {}

    /**
     * Extension du fichier gardé en mémoire.
     */
    public function extension(): string
    {
        return match (true) {
            str_contains($this->mime, 'mpeg'), str_contains($this->mime, 'mp3') => 'mp3',
            str_contains($this->mime, 'ogg') || str_contains($this->mime, 'opus') => 'ogg',
            default => 'wav',
        };
    }

    public static function mimeFor(string $extension): string
    {
        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            default => 'audio/wav',
        };
    }
}
