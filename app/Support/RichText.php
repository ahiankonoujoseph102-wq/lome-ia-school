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
        /** @var list<string> $paragraph */
        $paragraph = [];
        /** @var list<string> $items */
        $items = [];

        $close = function () use (&$blocks, &$paragraph, &$items): void {
            if ($paragraph !== []) {
                $blocks[] = ['type' => 'paragraph', 'lines' => $paragraph];
            }

            if ($items !== []) {
                $blocks[] = ['type' => 'list', 'items' => $items];
            }

            $paragraph = [];
            $items = [];
        };

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                $close();

                continue;
            }

            if (preg_match('/^[-–—•]\s*(.+)$/u', $line, $match) === 1) {
                if ($paragraph !== []) {
                    $close();
                }

                $items[] = trim($match[1]);

                continue;
            }

            if ($items !== []) {
                $close();
            }

            $paragraph[] = $line;
        }

        $close();

        return $blocks;
    }
}
