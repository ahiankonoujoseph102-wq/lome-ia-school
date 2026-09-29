<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Nettoyage d'un lien collé ou tapé sur téléphone, avant sa vérification.
 *
 * - caractères invisibles retirés (largeur nulle, marque d'ordre, trait d'union conditionnel) et
 *   espaces spéciaux (insécable, fine…) changés en espaces ordinaires, puis espaces du début et de la fin retirés ;
 * - lien extrait d'un texte plus long (« Rejoindre avec Google Meet : https://meet.google.com/… ») ;
 * - majuscules mises par le clavier retirées du « https:// » (« http:// » devient « https:// ») et du nom de domaine (le reste du lien est gardé tel quel :
 *   les identifiants YouTube et Drive distinguent majuscules et minuscules) ;
 * - « https:// » ajouté s'il manque (« meet.google.com/abc-defg-hij »).
 *
 * Un texte qui ne contient aucun lien est rendu nettoyé, sans autre changement (un identifiant YouTube seul reste valable).
 */
class PastedLink
{
    /**
     * Nom de domaine suivi (ou non) d'un chemin : www.youtube.com/watch?v=…, meet.google.com/abc-defg-hij.
     */
    private const string DOMAIN = '(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}';

    public static function clean(?string $value): string
    {
        $value = (string) $value;

        if (! mb_check_encoding($value, 'UTF-8')) {
            return trim($value);
        }

        // Caractères de largeur nulle, marques de direction, trait d'union conditionnel, BOM : supprimés.
        $value = (string) preg_replace('/[\x{00AD}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value);
        // Espaces spéciaux (insécable, fine, idéographique…) et retours à la ligne : espace ordinaire.
        $value = trim((string) preg_replace('/[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u', ' ', $value));

        if ($value === '') {
            return '';
        }

        $link = self::extract($value);

        if ($link === null) {
            return $value;
        }

        // « Https:// », « HTTPS:// », « http:// » ou rien : toujours « https:// » (accepté par tous ces services).
        $link = (string) preg_replace('~^https?:/+~i', '', $link, 1);

        // Nom de domaine en minuscules (le chemin garde ses majuscules).
        $link = (string) preg_replace_callback('~^[^/?#]+~', fn (array $host): string => mb_strtolower($host[0]), $link, 1);

        return 'https://'.$link;
    }

    /**
     * Nettoie, dans la requête, les champs de lien donnés (notation pointée : social.facebook, videos.*),
     * avant la validation. Les fichiers envoyés ne sont pas touchés.
     *
     * @param  list<string>  $keys
     */
    public static function cleanRequest(Request $request, array $keys): void
    {
        $input = $request->input();

        foreach ($keys as $key) {
            if (str_ends_with($key, '.*')) {
                $parent = substr($key, 0, -2);
                $values = data_get($input, $parent);

                if (is_array($values)) {
                    foreach ($values as $index => $item) {
                        if (is_string($item)) {
                            data_set($input, $parent.'.'.$index, self::clean($item));
                        }
                    }
                }

                continue;
            }

            $current = data_get($input, $key);

            if (is_string($current)) {
                data_set($input, $key, self::clean($current));
            }
        }

        $request->replace($input);
    }

    /**
     * Le lien contenu dans le texte, sans la ponctuation qui l'entoure, ou null s'il n'y en a pas.
     */
    private static function extract(string $value): ?string
    {
        // Un lien complet, même collé à un mot (« Meet:https://… »).
        if (preg_match('~https?:/+\S+~i', $value, $match)) {
            return self::trimPunctuation($match[0]);
        }

        // Un nom de domaine sans « https:// », de préférence suivi d'un chemin (meet.google.com/abc-defg-hij).
        foreach (['[/?#]\S*', ''] as $path) {
            if (preg_match('~(?<![\w@./-])'.self::DOMAIN.$path.'(?![\w@-])~i', $value, $match)) {
                return self::trimPunctuation($match[0]);
            }
        }

        return null;
    }

    private static function trimPunctuation(string $link): string
    {
        return rtrim(ltrim($link, '<(["\'«“'), '.,;:!?)]>"\'»”');
    }
}
