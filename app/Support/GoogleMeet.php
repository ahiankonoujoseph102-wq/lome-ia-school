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
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('~^(?:https?://)?meet\.google\.com/([a-z]{3}-[a-z]{4}-[a-z]{3})(?:[/?#][^\s]*)?$~i', $value, $match)) {
            return null;
        }

        return 'https://meet.google.com/'.strtolower($match[1]);
    }
}
