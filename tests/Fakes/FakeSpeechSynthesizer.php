<?php

namespace Tests\Fakes;

use App\Assistant\SpeechAudio;
use App\Assistant\SpeechFailure;
use App\Contracts\SpeechSynthesizer;

/**
 * Faux Google pour la voix naturelle d'Ama : audio WAV immédiat (ou après un délai), ou refus.
 */
class FakeSpeechSynthesizer implements SpeechSynthesizer
{
    public int $calls = 0;

    public int $delayMs = 0;

    public ?SpeechFailure $failure = null;

    public function isConfigured(): bool
    {
        return true;
    }

    public function synthesize(string $text, string $voice, string $model): SpeechAudio
    {
        $this->calls++;

        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new SpeechAudio('RIFF'.str_repeat("\x00", 40), 'audio/wav');
    }
}
