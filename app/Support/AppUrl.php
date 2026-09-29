<?php

namespace App\Support;

/**
 * Adresses absolues fondées sur APP_URL (liens partagés, balises Open Graph) : un lien copié ou l'aperçu
 * d'un partage WhatsApp ou Facebook pointe toujours vers l'adresse officielle du site, quelle que soit
 * l'adresse par laquelle la page a été ouverte.
 */
class AppUrl
{
    /**
     * Remplace le début (https://hôte) d'une adresse du site par celui d'APP_URL, en gardant le chemin.
     * Une adresse extérieure (image sur un stockage objet, par exemple) est laissée telle quelle.
     */
    public static function to(string $url): string
    {
        $root = self::origin(rtrim((string) config('app.url'), '/'));
        $origin = self::origin($url);

        if ($root === null || $origin === null || strcasecmp($origin, (string) self::origin(url('/'))) !== 0) {
            return $url;
        }

        return $root.substr($url, strlen($origin));
    }

    private static function origin(string $url): ?string
    {
        return preg_match('#^https?://[^/?\#]+#i', $url, $match) ? $match[0] : null;
    }
}
