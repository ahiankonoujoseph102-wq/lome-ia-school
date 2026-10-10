<?php

namespace App\Packs;

use App\Support\FileStorage;

/**
 * Page des offres d'accompagnement (/accompagnement) : affiche du haut de page (réglage « site.packs_poster »).
 */
class PacksPage
{
    /**
     * Chemin de l'affiche sur le disque « public », ou null si aucune n'est enregistrée.
     */
    public static function posterPath(): ?string
    {
        $path = trim((string) config('site.packs_poster'));

        return $path !== '' ? $path : null;
    }

    /**
     * Adresse de l'affiche, ou null (aucune affiche, ou fichier introuvable) : la page s'affiche alors sans image.
     */
    public static function posterUrl(): ?string
    {
        return FileStorage::publicUrl(self::posterPath());
    }
}
