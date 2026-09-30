<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lomé IA School est une école récente, distincte de Joseph Academy (autre activité de Joseph : écriture de livres
 * pour des clients). 2023 est l'année où Joseph a commencé à former, pas la création de l'école.
 *
 * Pour les réglages déjà enregistrés depuis l'admin, seuls les textes d'origine exacts sont corrigés ; un texte
 * modifié à la main n'est jamais touché :
 * - deuxième chiffre clé (« site.stats ») : seulement s'il vaut encore exactement « 2023 » avec le libellé
 *   « année de création de l'école », le libellé devient « année où Joseph a commencé à former » ;
 * - « L'école et son fondateur » d'Ama (« site.assistant_knowledge.school ») : seulement si la phrase d'origine exacte
 *   « Elle a été créée en 2023. Son ancien nom était Joseph Academy. » est encore présente, elle est remplacée
 *   sur place ; le reste du texte, les questions fréquentes et les autres informations restent intacts.
 * Rien n'est effacé. Une ligne qui ne change pas n'est pas réécrite. Sans réglage en base, le site lit directement
 * config/site.php, déjà à jour. Relancer la migration ne change plus rien.
 */
return new class extends Migration
{
    private const string OLD_STAT_VALUE = '2023';

    private const string OLD_STAT_LABEL = 'année de création de l\'école';

    private const string NEW_STAT_LABEL = 'année où Joseph a commencé à former';

    public const string OLD_SCHOOL_SENTENCE = 'Elle a été créée en 2023. Son ancien nom était Joseph Academy.';

    public const string NEW_SCHOOL_SENTENCE = 'C\'est une école récente. Joseph Academy est une autre activité de Joseph (écriture de livres pour des clients), distincte de Lomé IA School : ce n\'est pas l\'ancien nom de l\'école.';

    public function up(): void
    {
        $this->update('site.stats', function (array $stats): array {
            foreach ($stats as $index => $stat) {
                if (is_array($stat) && ($stat['value'] ?? null) === self::OLD_STAT_VALUE && ($stat['label'] ?? null) === self::OLD_STAT_LABEL) {
                    $stats[$index]['label'] = self::NEW_STAT_LABEL;
                }
            }

            return $stats;
        });

        $this->update('site.assistant_knowledge', function (array $knowledge): array {
            $school = $knowledge['school'] ?? null;

            if (is_string($school) && str_contains($school, self::OLD_SCHOOL_SENTENCE)) {
                $knowledge['school'] = str_replace(self::OLD_SCHOOL_SENTENCE, self::NEW_SCHOOL_SENTENCE, $school);
            }

            return $knowledge;
        });

        Cache::forget('site-settings');
    }

    /**
     * @param  callable(array<array-key, mixed>): array<array-key, mixed>  $change
     */
    private function update(string $key, callable $change): void
    {
        $row = DB::table('site_settings')->where('key', $key)->first();
        $value = $row !== null ? json_decode((string) $row->value, true) : null;

        if (! is_array($value)) {
            return;
        }

        $changed = $change($value);

        if ($changed !== $value) {
            DB::table('site_settings')->where('key', $key)->update([
                'value' => json_encode($changed, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }
};
