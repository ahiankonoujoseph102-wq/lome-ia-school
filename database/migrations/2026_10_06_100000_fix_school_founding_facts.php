<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lomé IA School est une école récente, distincte de Joseph Academy (autre activité de Joseph : écriture de livres
 * pour des clients). 2023 est l'année où Joseph a commencé à former, pas la création de l'école. Pour les réglages
 * déjà enregistrés depuis l'admin :
 * - deuxième chiffre clé (« site.stats ») : « année de création de l'école » devient « année où Joseph a commencé à
 *   former » (la valeur, l'année, est gardée telle quelle) ;
 * - « L'école et son fondateur » d'Ama (« site.assistant_knowledge ») : la phrase « Elle a été créée en 2023. Son
 *   ancien nom était Joseph Academy. » est remplacée, sans toucher au reste du texte ;
 * - année de création de la page À propos (« site.about.founded ») retirée (plus affichée).
 * Sans réglage en base, le site lit directement config/site.php, déjà à jour.
 */
return new class extends Migration
{
    private const string OLD_STAT_LABEL = 'année de création de l\'école';

    private const string NEW_STAT_LABEL = 'année où Joseph a commencé à former';

    private const string OLD_SCHOOL_SENTENCE = 'Elle a été créée en 2023. Son ancien nom était Joseph Academy.';

    private const string NEW_SCHOOL_SENTENCE = 'C\'est une école récente.';

    private const string JOSEPH_ACADEMY = 'Joseph Academy est une autre activité de Joseph (écriture de livres pour des clients), distincte de Lomé IA School : ce n\'est pas l\'ancien nom de l\'école.';

    public function up(): void
    {
        $this->update('site.stats', function (array $stats): array {
            foreach ($stats as $index => $stat) {
                if (is_array($stat) && ($stat['label'] ?? null) === self::OLD_STAT_LABEL) {
                    $stats[$index]['label'] = self::NEW_STAT_LABEL;
                }
            }

            return $stats;
        });

        $this->update('site.assistant_knowledge', function (array $knowledge): array {
            $school = $knowledge['school'] ?? null;

            if (is_string($school) && str_contains($school, self::OLD_SCHOOL_SENTENCE)) {
                $knowledge['school'] = str_replace(self::OLD_SCHOOL_SENTENCE, self::NEW_SCHOOL_SENTENCE, $school);

                if (! str_contains($knowledge['school'], 'Joseph Academy est une autre activité')) {
                    $knowledge['school'] = rtrim($knowledge['school'])."\n".self::JOSEPH_ACADEMY;
                }
            }

            return $knowledge;
        });

        $this->update('site.about', function (array $about): array {
            unset($about['founded']);

            return $about;
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
