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
    ];

    public static function enabled(string $feature): bool
    {
        return (bool) (((array) config('site.chat_features'))[$feature] ?? false);
    }

    public static function voicePause(): bool
    {
        return self::enabled(self::VOICE_PAUSE);
    }
}
