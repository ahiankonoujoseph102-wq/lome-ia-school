<?php

namespace App\Contracts;

/**
 * Fournisseur des vidéos de leçons (YouTube aujourd'hui, Bunny.net plus tard).
 */
interface VideoProvider
{
    /**
     * Clé enregistrée avec la leçon (ex. « youtube »).
     */
    public function key(): string;

    /**
     * Nom affiché à l'admin (ex. « YouTube »).
     */
    public function name(): string;

    /**
     * Identifiant de la vidéo à partir du lien collé par l'admin, ou null si le lien n'est pas reconnu.
     */
    public function parse(string $input): ?string;

    /**
     * Adresse du lecteur intégré, réglé pour limiter les liens sortants et les suggestions.
     */
    public function embedUrl(string $videoId): string;

    public function thumbnailUrl(string $videoId): ?string;
}
