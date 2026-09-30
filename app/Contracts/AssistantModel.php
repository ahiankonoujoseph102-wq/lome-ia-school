<?php

namespace App\Contracts;

use App\Assistant\AssistantFailure;

/**
 * Modèle d'IA qui répond aux visiteurs pour l'assistante « Ama ». Choisi par la configuration :
 * App\Assistant\GeminiModel (API Gemini de Google). Appelé uniquement par le serveur.
 */
interface AssistantModel
{
    /**
     * La clé d'API est-elle présente ?
     */
    public function isConfigured(): bool;

    /**
     * Réponse d'Ama à la conversation.
     *
     * @param  list<array{role: string, text: string}>  $messages  « user » (visiteur) ou « model » (Ama), du plus ancien au plus récent.
     *
     * @throws AssistantFailure
     */
    public function reply(string $instructions, array $messages): string;
}
