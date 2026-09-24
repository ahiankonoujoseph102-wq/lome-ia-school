<?php

namespace App\Support;

class YouTube
{
    /**
     * Identifiant d'une vidéo à partir d'un lien YouTube (watch, youtu.be, shorts, embed) ou de l'identifiant seul.
     */
    public static function id(?string $value): ?string
    {
        $value = trim((string) $value);

        if (preg_match('/^[\w-]{11}$/', $value)) {
            return $value;
        }

        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Adresse du lecteur intégré, sans cookie publicitaire tant que la vidéo n'est pas lancée.
     */
    public static function embedUrl(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0';
    }

    /**
     * Miniature de la vidéo, affichée avant le lancement pour garder la page légère.
     */
    public static function thumbnailUrl(string $id): string
    {
        return 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
    }
}
