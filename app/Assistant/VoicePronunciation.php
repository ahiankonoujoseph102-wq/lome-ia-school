<?php

namespace App\Assistant;

/**
 * Table de prononciation de la voix naturelle d'Ama (Réglages du site > Assistante Ama > Voix naturelle, clé
 * `site.voice_assistant.natural_voice.pronunciations`) : « ce qui est écrit » → « comment le dire ».
 *
 * Appliquée UNIQUEMENT au texte envoyé au fournisseur de voix (Cloudflare ou Google), juste avant l'envoi : le texte
 * affiché, la voix du téléphone et tout le reste ne changent jamais.
 *
 * Règles : mots entiers seulement (le « ia » de « confiance » n'est jamais touché) ; une expression qui contient au moins
 * deux majuscules (sigle : « IA », « FCFA », « ChatGPT », « Lomé IA School ») n'est remplacée que si elle est écrite
 * exactement pareil, les autres quelle que soit la casse ; les expressions les plus longues d'abord, en un seul passage
 * (un texte déjà remplacé n'est jamais remplacé une deuxième fois).
 */
final class VoicePronunciation
{
    /**
     * Valeurs de départ (modifiables dans l'admin). Rien d'enregistré = cette table.
     *
     * @var list<array{written: string, spoken: string}>
     */
    public const array DEFAULTS = [
        ['written' => 'IA', 'spoken' => 'i a'],
        ['written' => 'Lomé IA School', 'spoken' => 'Lomé i a Scoul'],
        ['written' => 'ChatGPT', 'spoken' => 'Tchate G P T'],
        ['written' => 'SaaS', 'spoken' => 'sasse'],
        ['written' => 'FCFA', 'spoken' => 'francs C F A'],
    ];

    public const int MAX_ROWS = 60;

    public const int MAX_LENGTH = 80;

    /**
     * Table en vigueur : celle enregistrée dans l'admin (même vide), sinon les valeurs de départ.
     *
     * @return list<array{written: string, spoken: string}>
     */
    public static function table(): array
    {
        $saved = config('site.voice_assistant.natural_voice.pronunciations');

        return is_array($saved) ? self::clean($saved) : self::DEFAULTS;
    }

    /**
     * Lignes nettoyées : espaces (y compris insécables) ramenés à un seul, lignes incomplètes ignorées, une seule ligne
     * par expression écrite (la première).
     *
     * @param  array<mixed>  $rows
     * @return list<array{written: string, spoken: string}>
     */
    public static function clean(array $rows): array
    {
        $table = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $written = self::squash($row['written'] ?? '');
            $spoken = self::squash($row['spoken'] ?? '');

            if ($written === '' || $spoken === '' || preg_match('/[\p{L}\p{N}]/u', $written) !== 1 || isset($seen[$written])) {
                continue;
            }

            $seen[$written] = true;
            $table[] = ['written' => $written, 'spoken' => $spoken];

            if (count($table) >= self::MAX_ROWS) {
                break;
            }
        }

        return $table;
    }

    /**
     * Texte à envoyer à la voix : chaque expression de la table remplacée par sa prononciation.
     *
     * @param  list<array{written: string, spoken: string}>|null  $table  table en cours de saisie (bouton « Écouter »),
     *                                                                    sinon celle enregistrée
     */
    public static function apply(string $text, ?array $table = null): string
    {
        $table ??= self::table();

        if ($table === [] || $text === '') {
            return $text;
        }

        // Les plus longues d'abord : « Lomé IA School » passe avant « IA ».
        usort($table, fn (array $a, array $b): int => mb_strlen($b['written']) <=> mb_strlen($a['written']));

        $alternatives = [];

        foreach ($table as $row) {
            // Espaces de l'expression : n'importe quel espace (y compris insécable) dans le texte.
            $pattern = implode('\s+', array_map(fn (string $part): string => preg_quote($part, '/'), preg_split('/\s+/u', $row['written']) ?: []));
            $alternatives[] = self::isCaseSensitive($row['written']) ? '('.$pattern.')' : '((?i:'.$pattern.'))';
        }

        $regex = '/(?<![\p{L}\p{N}])(?:'.implode('|', $alternatives).')(?![\p{L}\p{N}])/u';

        $result = preg_replace_callback($regex, function (array $match) use ($table): string {
            foreach ($table as $index => $row) {
                if (($match[$index + 1] ?? null) !== null) {
                    return $row['spoken'];
                }
            }

            return $match[0];
        }, $text, flags: PREG_UNMATCHED_AS_NULL);

        return is_string($result) ? $result : $text;
    }

    /**
     * Sigle ou nom de marque (au moins deux majuscules) : remplacé seulement s'il est écrit exactement pareil.
     */
    public static function isCaseSensitive(string $written): bool
    {
        return preg_match_all('/\p{Lu}/u', $written) >= 2;
    }

    private static function squash(mixed $value): string
    {
        return is_string($value) ? trim((string) preg_replace('/[\s\x{00A0}\x{202F}\x{200B}]+/u', ' ', $value)) : '';
    }
}
