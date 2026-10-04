<?php

namespace App\Contracts;

use App\Assistant\SpeechAudio;
use App\Assistant\SpeechFailure;

/**
 * Fabrique la voix d'Ama à partir d'un texte (« voix naturelle »). Choisi par la configuration :
 * App\Assistant\GeminiSpeech (modèles « TTS » de Gemini). Appelé uniquement par le serveur : la clé d'API ne va
 * jamais dans le navigateur.
 */
interface SpeechSynthesizer
{
    /**
     * La clé d'API est-elle présente ?
     */
    public function isConfigured(): bool;

    /**
     * Audio du texte, lu par la voix et le modèle choisis.
     *
     * @throws SpeechFailure
     */
    public function synthesize(string $text, string $voice, string $model): SpeechAudio;
}
