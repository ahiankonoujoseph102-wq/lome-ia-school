<?php

namespace App\Support;

use App\Models\Formation;

/**
 * Partage d'une formation (bouton « Partager cette formation », « Copier le lien » de l'admin) :
 * lien de la page publique, message WhatsApp et image d'aperçu (Open Graph).
 *
 * Le message ne contient que le nom de la formation et le lien : aucune promesse.
 */
class FormationShare
{
    /**
     * Adresse de la page publique de la formation (détails, prix, bouton d'achat), fondée sur APP_URL.
     */
    public static function url(Formation $formation): string
    {
        return AppUrl::to(route('formations.show', $formation));
    }

    public static function message(Formation $formation): string
    {
        return $formation->title."\n".self::url($formation);
    }

    public static function whatsappUrl(Formation $formation): string
    {
        return 'https://wa.me/?text='.rawurlencode(self::message($formation));
    }

    public static function facebookUrl(Formation $formation): string
    {
        return 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode(self::url($formation));
    }

    /**
     * Image d'aperçu (1200 × 630) : l'image de la formation si elle en a une, sinon le visuel généré
     * avec le logo, le titre et le prix. Le numéro de version force WhatsApp et Facebook à la recharger.
     */
    public static function imageUrl(Formation $formation): string
    {
        $route = filled($formation->image) ? 'og.formation.photo' : 'og.formation';

        return AppUrl::to(route($route, $formation)).'?v='.$formation->updated_at?->timestamp;
    }

    public static function imageType(Formation $formation): string
    {
        return filled($formation->image) ? 'image/jpeg' : 'image/png';
    }
}
