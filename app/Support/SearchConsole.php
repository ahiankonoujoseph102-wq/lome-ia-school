<?php

namespace App\Support;

/**
 * Balise de vérification de Google Search Console (méthode « Balise HTML »), ajoutée dans toutes les pages
 * quand le code est saisi dans l'admin (Réglages du site > Référencement Google). Ce code est public.
 */
class SearchConsole
{
    /**
     * Code enregistré, ou null s'il est vide ou invalide (aucune balise alors).
     */
    public static function code(): ?string
    {
        $code = self::clean((string) config('site.seo.google_verification'));

        return self::isValid($code) ? $code : null;
    }

    /**
     * Nettoie ce que Joseph colle : la balise entière (<meta name="google-site-verification" content="…" />),
     * seulement le code, ou le code entre guillemets ; caractères invisibles et espaces retirés.
     */
    public static function clean(?string $value): string
    {
        $value = (string) preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+/u', ' ', (string) $value);

        if (preg_match('/content\s*=\s*["\']?([^"\'\s>]+)/i', $value, $match)) {
            $value = $match[1];
        }

        return trim($value, " \"'");
    }

    public static function isValid(string $code): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{10,100}$/', $code) === 1;
    }

    public static function invalidMessage(): string
    {
        return 'Code non reconnu. Collez la balise donnée par Google (<meta name="google-site-verification" content="…" />) ou seulement le code entre les guillemets de content.';
    }

    /**
     * Adresse du plan du site à déclarer dans Search Console (fondée sur APP_URL).
     */
    public static function sitemapUrl(): string
    {
        return AppUrl::to(route('sitemap'));
    }
}
