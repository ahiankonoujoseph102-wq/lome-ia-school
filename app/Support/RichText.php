<?php

namespace App\Support;

/**
 * Texte saisi dans l'admin (description d'une offre…) découpé en blocs pour l'affichage, sans jamais
 * interpréter de HTML : la vue échappe chaque ligne.
 *
 * - Les retours à la ligne saisis sont gardés ; une ligne vide sépare deux paragraphes.
 * - Une ligne qui commence par un tiret (« - », « – », « — ») ou une puce (« • ») devient un élément de liste ;
 *   les lignes de ce type qui se suivent forment une seule liste.
 */
class RichText
{
    /**
     * @return list<array{type: 'paragraph', lines: list<string>}|array{type: 'list', items: list<string>}>
     */
    public static function blocks(?string $text): array
    {
        $lines = preg_split('/\R/u', trim((string) $text)) ?: [];
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                $current = null;

                continue;
            }

            if (preg_match('/^[-–—•]\s*(.+)$/u', $line, $match) === 1) {
                if ($current === null || $blocks[$current]['type'] !== 'list') {
                    $blocks[] = ['type' => 'list', 'items' => []];
                    $current = array_key_last($blocks);
                }

                $blocks[$current]['items'][] = trim($match[1]);

                continue;
            }

            if ($current === null || $blocks[$current]['type'] !== 'paragraph') {
                $blocks[] = ['type' => 'paragraph', 'lines' => []];
                $current = array_key_last($blocks);
            }

            $blocks[$current]['lines'][] = $line;
        }

        return $blocks;
    }
}
