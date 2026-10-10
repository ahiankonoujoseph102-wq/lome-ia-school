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

    /**
     * Résumé d'une carte d'offre : la première ligne (ou le premier paragraphe) sert d'accroche, la première
     * liste donne les points forts ($max au plus) ; tout le reste (paragraphes suivants, points en trop) est
     * renvoyé à part, pour être affiché replié sous « Voir tout ce qui est inclus » : rien n'est perdu.
     *
     * @return array{hook: list<string>, points: list<string>, rest: list<array{type: 'paragraph', lines: list<string>}|array{type: 'list', items: list<string>}>}
     */
    public static function summary(?string $text, int $max = 4): array
    {
        $blocks = self::blocks($text);
        $hook = [];
        $points = [];
        $rest = [];

        foreach ($blocks as $index => $block) {
            if ($index === 0 && $block['type'] === 'paragraph') {
                $hook = $block['lines'];

                continue;
            }

            if ($points === [] && $block['type'] === 'list') {
                $points = array_slice($block['items'], 0, $max);
                $more = array_slice($block['items'], $max);

                if ($more !== []) {
                    $rest[] = ['type' => 'list', 'items' => $more];
                }

                continue;
            }

            $rest[] = $block;
        }

        return ['hook' => $hook, 'points' => $points, 'rest' => $rest];
    }
}
