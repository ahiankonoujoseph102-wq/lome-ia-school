<?php

namespace App\Support;

/**
 * Liens Google Meet : https://meet.google.com/abc-defg-hij (code à 10 lettres en 3 groupes).
 */
class GoogleMeet
{
    /**
     * Lien propre (https, sans espaces), ou null s'il ne s'agit pas d'un lien de réunion Google Meet.
     */
    public static function normalize(?string $value): ?string
    {
        $value = PastedLink::clean($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('~^(?:https?://)?meet\.google\.com/([a-z]{3}-[a-z]{4}-[a-z]{3})(?:[/?#][^\s]*)?$~i', $value, $match)) {
            return null;
        }

        return 'https://meet.google.com/'.strtolower($match[1]);
    }

    /**
     * Message affiché sous le champ quand le lien n'est pas reconnu, avec ce qu'il faut corriger.
     */
    public static function invalidMessage(?string $value): string
    {
        $example = 'Le lien Google Meet doit ressembler à https://meet.google.com/abc-defg-hij';

        if (str_contains(strtolower(PastedLink::clean($value)), 'meet.google.com')) {
            return $example.' : 3 lettres, un tiret, 4 lettres, un tiret, 3 lettres. Vérifiez qu\'il ne manque aucune lettre du code de la réunion.';
        }

        return $example.'. Dans Google Meet, touchez « Partager les informations de connexion » ou « Copier le lien ».';
    }
}
