<?php

namespace App\Contracts;

/**
 * Fournisseur des vidéos de leçons (YouTube et Google Drive aujourd'hui, Bunny.net plus tard).
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
     * Message d'erreur précis quand le lien n'est pas reconnu.
     */
    public function invalidLinkMessage(string $input): string;

    /**
     * Aide affichée sous le champ du lien, dans l'écran admin.
     */
    public function linkHelp(): string;

    /**
     * Rappel important affiché à l'admin (ex. partage du fichier), ou null.
     */
    public function adminReminder(): ?string;

    /**
     * Adresse du lecteur intégré, réglé pour limiter les liens sortants et les suggestions.
     */
    public function embedUrl(string $videoId): string;

    /**
     * Lien à recoller dans le formulaire pour modifier la leçon.
     */
    public function shareUrl(string $videoId): string;

    public function thumbnailUrl(string $videoId): ?string;
}
