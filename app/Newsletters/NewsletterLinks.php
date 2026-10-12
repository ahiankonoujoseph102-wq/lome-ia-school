<?php

namespace App\Newsletters;

use App\Support\AppUrl;
use App\Support\Brand;
use App\Support\InternalPath;
use App\Support\YouTube;

/**
 * Liens d'un envoi groupé : vidéo (YouTube ou page du site) et bouton d'action (page du site ou adresse https).
 * Les valeurs ont déjà été nettoyées par App\Support\PastedLink avant la vérification.
 */
class NewsletterLinks
{
    public const string VIDEO_INVALID = 'Collez un lien YouTube (par exemple https://www.youtube.com/watch?v=…) ou l\'adresse d\'une page du site (par exemple https://lomeiaschool.laravel.cloud/accompagnement).';

    public const string BUTTON_INVALID = 'Collez l\'adresse d\'une page du site (par exemple /accompagnement) ou un lien complet commençant par https://.';

    /**
     * @return array{url: string, thumbnail: string|null, youtube: bool}|null
     */
    public static function video(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $id = YouTube::id($value);

        if ($id !== null) {
            return ['url' => 'https://www.youtube.com/watch?v='.$id, 'thumbnail' => YouTube::thumbnailUrl($id), 'youtube' => true];
        }

        $path = InternalPath::clean($value);

        if ($path === null) {
            return null;
        }

        $image = Brand::url(config('site.brand.og_image'));

        return ['url' => AppUrl::to(url($path)), 'thumbnail' => $image !== null ? AppUrl::to($image) : null, 'youtube' => false];
    }

    public static function isValidVideo(?string $value): bool
    {
        return self::video($value) !== null;
    }

    /**
     * Adresse complète d'un bouton : page du site (« /accompagnement », adresse du site) ou lien https vers un autre site.
     */
    public static function absolute(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $path = InternalPath::clean($value);

        if ($path !== null) {
            return AppUrl::to(url($path));
        }

        return preg_match('~^https://[^/\s]+\.[^/\s]+~i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
    }
}
