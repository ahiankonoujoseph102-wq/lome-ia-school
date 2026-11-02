<?php

namespace App\Support;

class YouTube
{
    /**
     * Formats d'affichage d'une vidéo.
     *
     * @var list<string>
     */
    public const array FORMATS = ['horizontal', 'vertical'];

    /**
     * Identifiant d'une vidéo à partir d'un lien YouTube (normal, court, mobile, shorts, live, intégré) ou de l'identifiant seul.
     */
    public static function id(?string $value): ?string
    {
        $value = PastedLink::clean($value);

        if (preg_match('/^[\w-]{11}$/', $value)) {
            return $value;
        }

        // youtube.com (www., m., music.) : watch?v=, embed/, shorts/, live/, v/ ; youtu.be/ ; youtube-nocookie.com/embed/.
        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([\w-]{11})(?![\w-])~', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Message affiché sous le champ quand le lien YouTube n'est pas reconnu.
     */
    public static function invalidMessage(): string
    {
        return 'Le lien YouTube doit ressembler à https://www.youtube.com/watch?v=abcdefghijk ou https://youtu.be/abcdefghijk. Sur YouTube : « Partager », puis « Copier le lien ».';
    }

    /**
     * Lien YouTube Shorts (youtube.com/shorts/…) : vidéo verticale, filmée au téléphone.
     */
    public static function isShorts(?string $value): bool
    {
        return (bool) preg_match('~youtube\.com/shorts/[\w-]{11}~i', PastedLink::clean($value));
    }

    /**
     * Format d'affichage : « vertical » (9:16) ou « horizontal » (16:9). Sans choix enregistré,
     * un lien Shorts est affiché en vertical, tout autre lien en horizontal.
     */
    public static function format(?string $value, ?string $choice = null): string
    {
        if (in_array($choice, self::FORMATS, true)) {
            return $choice;
        }

        return self::isShorts($value) ? 'vertical' : 'horizontal';
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

    /**
     * Miniature en haute définition (1280 × 720, vraie image 16:9 sans bandes noires). YouTube ne la fabrique pas
     * toujours (vidéo ancienne ou de faible définition) : il répond alors par une petite image grise de 120 × 90 ;
     * le navigateur passe dans ce cas à thumbnailUrl() (composant x-site.lazy-youtube, méthode checkThumbnail).
     */
    public static function hdThumbnailUrl(string $id): string
    {
        return 'https://i.ytimg.com/vi/'.$id.'/maxresdefault.jpg';
    }

    /**
     * Miniature moyenne (640 × 480, 4:3 : l'image 16:9 entourée de bandes noires), premier repli de hdThumbnailUrl().
     */
    public static function sdThumbnailUrl(string $id): string
    {
        return 'https://i.ytimg.com/vi/'.$id.'/sddefault.jpg';
    }
}
