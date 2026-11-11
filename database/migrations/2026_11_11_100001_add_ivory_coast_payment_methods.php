<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Paiement depuis la Côte d'Ivoire (novembre 2026, numéros fournis par Joseph) pour les réglages déjà enregistrés
 * depuis l'admin (sans réglage en base, le site lit config/site.php, déjà à jour) :
 * - pays « ci » ajouté à « site.payments.countries » s'il manque ;
 * - moyens « ci-orange », « ci-mtn », « ci-wave » ajoutés à « site.payments.methods » s'ils manquent (un moyen déjà
 *   enregistré n'est jamais modifié) ;
 * - dans les textes du site enregistrés, seules les expressions exactes « Togo, Burkina Faso ou Bénin »,
 *   « Togo, Burkina Faso et Bénin » et « le Togo, le Burkina Faso ou le Bénin » complétées avec la Côte d'Ivoire.
 * Anciennes valeurs gardées dans « system.ivory_coast_previous » (une seule fois). Données seulement, relançable.
 */
return new class extends Migration
{
    private const array COUNTRY = ['name' => 'Côte d\'Ivoire', 'flag' => '🇨🇮', 'dial_code' => '+225', 'note' => 'Paiement reçu par notre représentant en Côte d\'Ivoire'];

    private const array METHODS = [
        'ci-orange' => ['country' => 'ci', 'name' => 'Orange Money', 'number' => '+225 07 09 51 82 85', 'beneficiary' => 'OULOUSSEGUEA CHARLES DANHAN', 'color' => '#f97316', 'ussd' => '', 'enabled' => true],
        'ci-mtn' => ['country' => 'ci', 'name' => 'MTN Mobile Money', 'number' => '+225 05 45 53 11 70', 'beneficiary' => 'OULOUSSEGUEA CHARLES DANHAN', 'color' => '#eab308', 'ussd' => '', 'enabled' => true],
        'ci-wave' => ['country' => 'ci', 'name' => 'Wave', 'number' => '+225 07 09 51 82 85', 'beneficiary' => 'OULOUSSEGUEA CHARLES DANHAN', 'color' => '#38bdf8', 'ussd' => '', 'enabled' => true],
    ];

    private const array PHRASES = [
        'Togo, Burkina Faso ou Bénin' => 'Togo, Burkina Faso, Bénin ou Côte d\'Ivoire',
        'Togo, Burkina Faso et Bénin' => 'Togo, Burkina Faso, Bénin et Côte d\'Ivoire',
        'le Togo, le Burkina Faso ou le Bénin' => 'le Togo, le Burkina Faso, le Bénin ou la Côte d\'Ivoire',
    ];

    private const string BACKUP = 'system.ivory_coast_previous';

    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('site_settings')->where('key', 'like', 'site.%')->get(['key', 'value']);
            $changes = [];

            foreach ($rows as $row) {
                $value = json_decode((string) $row->value, true);
                $new = $this->updated((string) $row->key, $value);

                if ($new !== $value) {
                    $changes[(string) $row->key] = [$value, $new];
                }
            }

            if ($changes === []) {
                return;
            }

            if (! DB::table('site_settings')->where('key', self::BACKUP)->exists()) {
                DB::table('site_settings')->insert([
                    'key' => self::BACKUP,
                    'value' => json_encode(array_map(fn (array $change): mixed => $change[0], $changes), JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($changes as $key => [, $new]) {
                DB::table('site_settings')->where('key', $key)->update([
                    'value' => json_encode($new, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        });

        Cache::forget('site-settings');
    }

    private function updated(string $key, mixed $value): mixed
    {
        if ($key === 'site.payments.countries' && is_array($value) && ! array_key_exists('ci', $value)) {
            $value['ci'] = self::COUNTRY;
        }

        if ($key === 'site.payments.methods' && is_array($value)) {
            foreach (self::METHODS as $methodKey => $method) {
                if (! array_key_exists($methodKey, $value)) {
                    $value[$methodKey] = $method;
                }
            }
        }

        return $this->replacePhrases($value);
    }

    private function replacePhrases(mixed $value): mixed
    {
        if (is_string($value)) {
            return strtr($value, self::PHRASES);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->replacePhrases($item), $value);
        }

        return $value;
    }
};
