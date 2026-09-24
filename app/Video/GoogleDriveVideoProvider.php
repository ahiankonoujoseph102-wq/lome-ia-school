<?php

namespace App\Video;

use App\Contracts\VideoProvider;
use App\Support\GoogleDrive;

/**
 * Vidéos Google Drive, lues dans le lecteur d'aperçu de Drive (/preview).
 * Le fichier doit être partagé en « Tous les utilisateurs disposant du lien ».
 */
class GoogleDriveVideoProvider implements VideoProvider
{
    public function key(): string
    {
        return 'drive';
    }

    public function name(): string
    {
        return 'Google Drive';
    }

    public function parse(string $input): ?string
    {
        return GoogleDrive::fileId($input);
    }

    public function invalidLinkMessage(string $input): string
    {
        if (GoogleDrive::isFolder($input)) {
            return 'C\'est le lien d\'un dossier : ouvrez la vidéo elle-même dans Drive, puis copiez son lien.';
        }

        return 'Ce lien Google Drive n\'est pas reconnu. Dans Drive : « Partager », puis « Copier le lien » de la vidéo.';
    }

    public function linkHelp(): string
    {
        return 'Dans Drive : touchez les trois points de la vidéo, « Partager », puis « Copier le lien ».';
    }

    public function adminReminder(): ?string
    {
        return 'Le fichier Drive doit être partagé en « Tous les utilisateurs disposant du lien ».';
    }

    public function embedUrl(string $videoId): string
    {
        return GoogleDrive::previewUrl($videoId);
    }

    public function shareUrl(string $videoId): string
    {
        return GoogleDrive::viewUrl($videoId);
    }

    public function thumbnailUrl(string $videoId): ?string
    {
        return null;
    }
}
