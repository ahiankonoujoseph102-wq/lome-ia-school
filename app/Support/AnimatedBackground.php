<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Fond animé de l'accueil et du haut du catalogue (réglage « Fond animé » de l'admin, clé `site.animated_background`).
 *
 * Uniquement des animations CSS (transform et opacité) ; aucune image ni vidéo de fond. Éteint dans l'admin :
 * l'ancien fond fixe revient. Visiteur en mode « économie de données » (en-tête Save-Data) : motifs fixes, sans animation.
 */
class AnimatedBackground
{
    /**
     * Seules pages où le fond peut apparaître (jamais paiement, connexion, cours, Mon espace, groupes ni admin).
     *
     * @var list<string>
     */
    public const array PAGES = ['home', 'formations.index'];

    /**
     * Icônes génériques qui flottent (dessinées dans site.css, aucun logo d'entreprise).
     *
     * @var list<string>
     */
    public const array ICONS = ['robot', 'chat', 'book', 'gear', 'phone', 'bolt'];

    public static function enabled(): bool
    {
        return (bool) (((array) config('site.animated_background'))['enabled'] ?? true);
    }

    public static function shownOn(?string $route): bool
    {
        return self::enabled() && in_array($route, self::PAGES, true);
    }

    public static function saveData(Request $request): bool
    {
        return strtolower(trim((string) $request->header('Save-Data'))) === 'on';
    }
}
