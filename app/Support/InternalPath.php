<?php

namespace App\Support;

/**
 * Adresse interne du site saisie dans l'admin (destination d'un bouton) : « /accompagnement »,
 * « accompagnement » ou l'adresse complète du site (« https://lomeiaschool.laravel.cloud/accompagnement »).
 *
 * Toujours ramenée à un chemin qui commence par une seule barre (« /accompagnement »), avec ses paramètres et son
 * ancre éventuels. Toute adresse vers un autre site est refusée (null) : autre nom de domaine, « //site.com »,
 * « javascript: », « mailto: », « tel: », barre oblique inversée, espaces au milieu.
 */
class InternalPath
{
    public const int MAX_LENGTH = 255;

    public static function clean(?string $value): ?string
    {
        // Caractères invisibles et espaces retirés, majuscules du « https:// » et du nom de domaine corrigées.
        $value = PastedLink::clean($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH || preg_match('/[\s\\\\\x00-\x1F\x7F]/u', $value)) {
            return null;
        }

        if (preg_match('#^https?://([^/?\#]+)(.*)$#i', $value, $match)) {
            if (! in_array(mb_strtolower($match[1]), self::ownHosts(), true)) {
                return null;
            }

            $value = $match[2];
        } elseif (str_starts_with($value, '//') || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
            // Adresse sans protocole vers un autre site (« //site.com ») ou autre protocole (javascript:, mailto:…).
            return null;
        }

        $value = '/'.ltrim($value, '/');

        // « /?x » ou « /#x » restent valables ; « //… » ne peut plus apparaître ici.
        return str_starts_with($value, '//') ? null : $value;
    }

    /**
     * Chemin sans paramètres ni ancre, sans barre finale : « /accompagnement ».
     */
    public static function route(string $path): string
    {
        $route = (string) preg_replace('/[?#].*$/', '', $path);

        return '/'.trim($route, '/');
    }

    /**
     * Noms d'hôte du site (avec le port s'il y en a un) : celui d'APP_URL et celui par lequel la page est ouverte.
     *
     * @return list<string>
     */
    private static function ownHosts(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $url): string => preg_match('#^https?://([^/?\#]+)#i', $url, $match) ? mb_strtolower($match[1]) : '',
            [(string) config('app.url'), url('/')],
        ))));
    }
}
