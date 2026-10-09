<?php

namespace App\Support;

/**
 * Liens Google Maps des lieux partagés dans les groupes. Aucune API ni clé : seulement un lien qui ouvre
 * Google Maps. Seuls les liens de Google Maps sont acceptés (domaine vérifié sur le serveur).
 */
class GoogleMaps
{
    public const int MAX_LENGTH = 500;

    /**
     * Fin de nom de domaine de Google : .com, .tg, .fr, .co.uk, .com.br…
     */
    private const string GOOGLE_TLD = '(?:com|[a-z]{2}|co\.[a-z]{2}|com\.[a-z]{2})';

    /**
     * Lien nettoyé (caractères invisibles, texte autour, https) s'il mène à Google Maps, sinon null.
     */
    public static function normalize(?string $value): ?string
    {
        $link = PastedLink::clean($value);

        return self::isValid($link) ? $link : null;
    }

    public static function isValid(string $link): bool
    {
        if ($link === '' || mb_strlen($link) > self::MAX_LENGTH || preg_match('/[\s<>"\']/u', $link) === 1) {
            return false;
        }

        $parts = parse_url($link);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        return match (true) {
            // Lien court de l'application Google Maps (« Partager » sur le téléphone).
            $host === 'maps.app.goo.gl' => strlen(trim($path, '/')) > 0,
            $host === 'goo.gl' => str_starts_with($path, '/maps/'),
            preg_match('/^maps\.google\.'.self::GOOGLE_TLD.'$/', $host) === 1 => true,
            preg_match('/^(?:www\.)?google\.'.self::GOOGLE_TLD.'$/', $host) === 1 => $path === '/maps' || str_starts_with($path, '/maps/'),
            default => false,
        };
    }

    /**
     * Lien vers une position du téléphone (latitude, longitude), sans clé.
     */
    public static function coordinatesUrl(float $latitude, float $longitude): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.self::coordinate($latitude).','.self::coordinate($longitude);
    }

    public static function coordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    public static function invalidMessage(): string
    {
        return 'Ce lien n\'est pas un lien Google Maps. Dans Google Maps, touchez « Partager », copiez le lien (par exemple https://maps.app.goo.gl/…) puis collez-le ici.';
    }
}
