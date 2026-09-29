<?php

namespace App\Support;

/**
 * Liens Google Drive : identifiant d'un fichier et reconnaissance des liens de partage.
 */
class GoogleDrive
{
    /**
     * Identifiant d'un fichier Drive à partir d'un lien de partage (file/d/…, open?id=…, uc?id=…,
     * docs.google.com/file/d/…, drive.usercontent.google.com) ou de l'identifiant seul.
     */
    public static function fileId(?string $value): ?string
    {
        $value = PastedLink::clean($value);

        if (preg_match('/^[\w-]{25,}$/', $value)) {
            return $value;
        }

        if (! self::isGoogleUrl($value) || self::isFolder($value)) {
            return null;
        }

        if (preg_match('~/file/(?:u/\d+/)?d/([\w-]{20,})~', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('~[?&]id=([\w-]{20,})~', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function isFolder(string $value): bool
    {
        return (bool) preg_match('~drive\.google\.com/(?:drive/(?:u/\d+/)?)?folders/~', PastedLink::clean($value));
    }

    /**
     * Lien vers un fichier, un dossier ou un document Google (Drive, Docs, Sheets, Slides).
     */
    public static function isGoogleUrl(string $value): bool
    {
        $value = PastedLink::clean($value);
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));

        return str_starts_with($value, 'https://')
            && in_array($host, ['drive.google.com', 'docs.google.com', 'drive.usercontent.google.com'], true);
    }

    public static function previewUrl(string $fileId): string
    {
        return 'https://drive.google.com/file/d/'.rawurlencode($fileId).'/preview';
    }

    public static function viewUrl(string $fileId): string
    {
        return 'https://drive.google.com/file/d/'.rawurlencode($fileId).'/view';
    }
}
