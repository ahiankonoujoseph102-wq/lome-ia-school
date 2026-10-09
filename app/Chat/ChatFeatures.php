<?php

namespace App\Chat;

/**
 * Nouvelles fonctions des groupes de discussion, chacune derrière son interrupteur (Réglages du site >
 * Fonctions des groupes, clé `site.chat_features`), toutes éteintes au départ. Éteinte : le bouton n'apparaît
 * pas et le serveur refuse la demande ; le groupe se comporte exactement comme avant.
 */
class ChatFeatures
{
    public const string VOICE_PAUSE = 'voice_pause';

    public const string LOCATION = 'location';

    public const string POLLS = 'polls';

    /**
     * Libellés de l'écran d'administration.
     *
     * @var array<string, array{title: string, text: string}>
     */
    public const array LABELS = [
        self::VOICE_PAUSE => [
            'title' => 'Pause des notes vocales',
            'text' => 'Pendant l\'enregistrement : « Pause » / « Reprendre » et « Annuler ». Avant l\'envoi : écouter, puis « Envoyer » ou « Supprimer ». Si le téléphone ne sait pas mettre en pause, l\'enregistrement reste comme aujourd\'hui.',
        ],
        self::LOCATION => [
            'title' => 'Partage de localisation',
            'text' => 'Bouton « Lieu » : position actuelle du téléphone ou lien Google Maps collé, avec un nom et une adresse facultatifs. Le message s\'affiche comme une carte avec « Ouvrir dans Google Maps » (aucune carte intégrée, aucune clé).',
        ],
        self::POLLS => [
            'title' => 'Sondages',
            'text' => 'Bouton « Sondage » réservé aux administrateurs (dont le formateur principal) : une question, 2 à 10 choix, une ou plusieurs réponses. Tous les membres votent, modifient ou retirent leur vote ; résultats mis à jour sans recharger ; clôture par l\'auteur ou un administrateur.',
        ],
    ];

    public static function enabled(string $feature): bool
    {
        return (bool) (((array) config('site.chat_features'))[$feature] ?? false);
    }

    public static function voicePause(): bool
    {
        return self::enabled(self::VOICE_PAUSE);
    }

    public static function location(): bool
    {
        return self::enabled(self::LOCATION);
    }

    public static function polls(): bool
    {
        return self::enabled(self::POLLS);
    }
}
