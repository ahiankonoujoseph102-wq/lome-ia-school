<?php

namespace App\Assistant;

/**
 * Repère les réponses où Ama dit qu'elle n'a pas l'information (pour la liste « Questions sans réponse » de
 * l'admin). Deux moyens : le repère technique [INCONNU] qu'elle place en tête de sa réponse (retiré avant
 * l'affichage), et, à défaut, quelques formules courantes.
 */
class UnansweredReply
{
    private const array PHRASES = [
        'je n\'ai pas cette information',
        'je n\'ai pas cette précision',
        'je n\'ai pas l\'information',
        'je ne dispose pas de cette information',
        'je ne dispose pas de l\'information',
        'je ne sais pas',
        'cette information n\'est pas disponible',
    ];

    /**
     * @return array{text: string, unanswered: bool} texte sans le repère, et vrai si Ama n'avait pas l'information
     */
    public static function inspect(string $reply): array
    {
        $marked = (bool) preg_match('/\[\s*INCONNU\s*\]/iu', $reply);
        $text = trim((string) preg_replace('/\s*\[\s*INCONNU\s*\]\s*/iu', ' ', $reply));

        if ($text === '') {
            $text = $reply;
        }

        $normalized = mb_strtolower(str_replace('’', '\'', $text));
        $phrase = collect(self::PHRASES)->contains(fn (string $phrase): bool => str_contains($normalized, $phrase));

        return ['text' => $text, 'unanswered' => $marked || $phrase];
    }
}
