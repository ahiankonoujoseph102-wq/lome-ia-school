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
            default => FileStorage::publicUrl($path),
        };
    }

    /**
     * Chemin du fichier sur le serveur (pour le servir directement), ou null
     * (fichier introuvable, ou envoyé sur un stockage objet : utiliser alors url()).
     */
    public static function path(?string $path): ?string
    {
        $path = trim((string) $path, '/');

        return match (true) {
            $path === '' => null,
            is_file(public_path($path)) => public_path($path),
            FileStorage::isLocal('public') && Storage::disk('public')->exists($path) => Storage::disk('public')->path($path),
            default => null,
        };
    }

    public static function logoUrl(): ?string
    {
        return self::url(config('site.brand.logo'));
    }

    /**
     * Version WebP du logo livré avec le site (même image, compressée : 8 Ko au lieu de 35 Ko), servie aux
     * navigateurs qui la lisent ; null pour un logo envoyé depuis l'admin (sans version WebP).
     */
    public static function logoWebpUrl(): ?string
    {
        $path = trim((string) config('site.brand.logo'), '/');
        $webp = preg_replace('/\.png$/i', '.webp', $path);

        return $webp !== $path && is_file(public_path($path)) && is_file(public_path((string) $webp)) ? asset((string) $webp) : null;
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
        $path = config('site.brand.og_image');

        return self::url($path) !== null ? self::mimeType((string) $path) : 'image/png';
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
