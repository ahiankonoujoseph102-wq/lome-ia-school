<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Logo, icônes et image de partage. Chaque réglage (config « site.brand », modifiable dans l'admin)
 * désigne soit un fichier livré avec le site (dossier public/, ex. « images/brand/logo-horizontal.png »),
 * soit un fichier envoyé depuis l'admin (disque « public »).
 */
class Brand
{
    /**
     * Adresse d'un fichier de marque, ou null s'il est introuvable.
     */
    public static function url(?string $path): ?string
    {
        $path = trim((string) $path, '/');

        return match (true) {
            $path === '' => null,
            is_file(public_path($path)) => asset($path),
            Storage::disk('public')->exists($path) => Storage::disk('public')->url($path),
            default => null,
        };
    }

    /**
     * Chemin du fichier sur le serveur (pour le servir directement), ou null.
     */
    public static function path(?string $path): ?string
    {
        $path = trim((string) $path, '/');

        return match (true) {
            $path === '' => null,
            is_file(public_path($path)) => public_path($path),
            Storage::disk('public')->exists($path) => Storage::disk('public')->path($path),
            default => null,
        };
    }

    public static function logoUrl(): ?string
    {
        return self::url(config('site.brand.logo'));
    }

    /**
     * Une icône du jeu en place (favicon-32.png, icon-192.png…) : jeu envoyé depuis l'admin, sinon celui du site.
     */
    public static function icon(string $file): string
    {
        $directory = trim((string) config('site.brand.icons', 'icons'), '/');

        return self::url($directory.'/'.$file) ?? asset('icons/'.$file);
    }

    /**
     * Type de l'image de partage du site, pour la balise og:image:type.
     */
    public static function shareImageType(): string
    {
        $path = self::path(config('site.brand.og_image'));

        return $path !== null ? self::mimeType($path) : 'image/png';
    }

    public static function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
