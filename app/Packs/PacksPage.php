<?php

namespace App\Packs;

use App\Models\Formation;
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

    /**
     * Au moins une offre d'accompagnement publiée (même close) : la page /accompagnement est ouverte au public et le
     * lien « Accompagnement » du menu est affiché (une offre close y montre « Inscriptions closes »).
     */
    public static function published(): bool
    {
        return Formation::query()->publishedPacks()->exists();
    }

    /**
     * Au moins une offre d'accompagnement en vente : publiée et inscriptions encore ouvertes (aucune date de fin, ou
     * date pas encore atteinte, voir Formation::registrationsClosed). Sert au bouton d'une formation gratuite terminée
     * et à Ama : quand toutes les offres sont closes, ils n'orientent plus vers l'accompagnement.
     */
    public static function onSale(): bool
    {
        return Formation::query()->publishedPacks()->get()->contains(fn (Formation $pack): bool => ! $pack->registrationsClosed());
    }

    /**
     * Nom court du niveau, affiché en titre de sa carte : ce qui suit le dernier « : » du titre
     * (« Accompagnement IA, niveau 1 : Créateur de contenu » → « Créateur de contenu ») ; le titre entier sinon.
     */
    public static function shortName(string $title): string
    {
        $position = mb_strrpos($title, ':');
        $short = $position === false ? '' : trim(mb_substr($title, $position + 1));

        return $short !== '' ? $short : $title;
    }
}
