<?php

namespace App\Assistant;

/**
 * Retire les données personnelles d'un message du visiteur avant de l'envoyer à Gemini et de l'enregistrer :
 * adresses e-mail, liens, numéros de téléphone (8 chiffres ou plus), codes (4 à 7 chiffres : code secret
 * Mobile Money, code de confirmation…) et ce qui suit « mot de passe », « code secret » ou « PIN ».
 * Les montants suivis de « FCFA », « F », « CFA », « francs » ou « XOF » et les années sont gardés.
 */
class PersonalData
{
    private const string CURRENCY = '(?!\s?(?:FCFA|CFA|F\b|francs?\b|XOF\b))';

    public static function redact(string $text): string
    {
        $text = (string) preg_replace('/[^\s@]+@[^\s@]+\.[^\s@]+/u', '[adresse e-mail]', $text);
        $text = (string) preg_replace('/(?:https?:\/\/|www\.)\S+/iu', '[lien]', $text);
        $text = self::redactSecrets($text);
        $text = (string) preg_replace('/(?>\+?\d(?:[\s.\-]?\d){7,})'.self::CURRENCY.'/iu', '[numéro]', $text);
        $text = (string) preg_replace_callback('/(?<![\p{L}\p{N}])(?>\d{4,7})(?![\p{L}\p{N}])'.self::CURRENCY.'/iu', function (array $match): string {
            $number = (int) $match[0];

            // Une année (« depuis 2023 ») n'est pas un code.
            return strlen($match[0]) === 4 && $number >= 1900 && $number <= 2099 ? $match[0] : '[code]';
        }, $text);

        return $text;
    }

    /**
     * Masque seulement les secrets : ce qui suit « mot de passe : … », « PIN = … », « code secret est … », ou un
     * mot contenant un chiffre juste après ces mots (jamais « mot de passe oublié », ni « Pinterest »).
     * Sert aussi aux questions transmises à Coach Joseph, qui gardent leurs numéros et adresses.
     */
    public static function redactSecrets(string $text): string
    {
        $secret = '/\b(mots? de passe|mdp|password|code secret|code pin|pin)\b';
        $text = (string) preg_replace($secret.'(\s*(?:est\s+|:|=)\s*)(?!\[)[^\s,;]+/iu', '$1$2[masqué]', $text);

        return (string) preg_replace($secret.'(\s+)(?!\[)(?=[^\s,;]*\d)[^\s,;]+/iu', '$1$2[masqué]', $text);
    }
}
