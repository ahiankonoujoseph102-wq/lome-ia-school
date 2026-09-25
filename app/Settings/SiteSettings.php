<?php

namespace App\Settings;

use App\Models\SiteSetting;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Réglages du site modifiables depuis l'admin, enregistrés en base (table « site_settings »).
 *
 * Chaque ligne remplace une valeur des fichiers config/*.php (« site.contact », « affiliate.rate »…) :
 * le reste du code continue de lire config(), sans rien savoir de la base. Les fichiers gardent les
 * valeurs de départ ; une modification enregistrée est prise en compte à la requête suivante
 * (cache vidé à chaque enregistrement).
 *
 * Les textes peuvent contenir des marqueurs remplacés automatiquement par la valeur du réglage :
 * {seuil}, {affiliation_taux}, {affiliation_jours_lien}, {affiliation_jours_dispo}, {affiliation_minimum}.
 */
class SiteSettings
{
    public const string CACHE_KEY = 'site-settings';

    /**
     * Marqueurs utilisables dans les textes, avec leur explication pour l'admin.
     *
     * @var array<string, string>
     */
    public const array TOKENS = [
        '{seuil}' => 'seuil de vérification des paiements (ex. 50 000 FCFA)',
        '{affiliation_taux}' => 'taux de commission d\'affiliation (ex. 10)',
        '{affiliation_jours_lien}' => 'durée de validité d\'un lien d\'affiliation, en jours (ex. 30)',
        '{affiliation_jours_dispo}' => 'délai avant qu\'une commission soit disponible, en jours (ex. 7)',
        '{affiliation_minimum}' => 'retrait minimum (ex. 2 000 FCFA)',
    ];

    /**
     * Valeurs d'origine des fichiers de configuration, avant tout remplacement (lues une seule fois).
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $defaults = [];

    /**
     * Application pour laquelle les valeurs d'origine ont été lues (une nouvelle application les relit).
     */
    private static ?int $application = null;

    /**
     * Applique les réglages enregistrés à la configuration, puis remplace les marqueurs dans les textes.
     * Appelé au démarrage de chaque requête (AppServiceProvider).
     */
    public static function apply(): void
    {
        self::rememberDefaults();

        foreach (self::stored() as $key => $value) {
            config([$key => $value]);
        }

        config(['site' => self::replaceTokens((array) config('site'))]);
    }

    /**
     * Valeur brute (avec ses marqueurs) : enregistrée en base, sinon celle du fichier de configuration.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $stored = self::stored();

        return array_key_exists($key, $stored) ? $stored[$key] : self::default($key, $default);
    }

    /**
     * Valeur d'origine du fichier de configuration.
     */
    public static function default(string $key, mixed $default = null): mixed
    {
        self::rememberDefaults();
        [$file, $path] = array_pad(explode('.', $key, 2), 2, null);

        $values = self::$defaults[$file] ?? [];

        return $path === null ? $values : Arr::get($values, $path, $default);
    }

    /**
     * Enregistre plusieurs réglages d'un coup, puis les applique immédiatement.
     *
     * @param  array<string, mixed>  $values  chemin de configuration => valeur
     */
    public static function save(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        self::flush();
    }

    /**
     * Vide le cache des réglages et les réapplique (effet immédiat).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);

        // Repartir des valeurs d'origine avant de réappliquer.
        foreach (self::$defaults as $file => $values) {
            config([$file => $values]);
        }

        self::apply();
    }

    /**
     * Réglages enregistrés en base (en cache). Base absente (installation, migrations) : aucun réglage.
     *
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        try {
            /** @var array<string, mixed> */
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => SiteSetting::query()
                ->orderBy('key')
                ->get()
                ->mapWithKeys(fn (SiteSetting $setting): array => [$setting->key => $setting->value])
                ->all());
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * Remplace les marqueurs {…} dans tous les textes (tableaux parcourus récursivement).
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function replaceTokens(array $values): array
    {
        $tokens = self::tokenValues();

        array_walk_recursive($values, function (mixed &$value) use ($tokens): void {
            if (is_string($value) && str_contains($value, '{')) {
                $value = strtr($value, $tokens);
            }
        });

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public static function tokenValues(): array
    {
        return [
            '{seuil}' => Money::fcfa((int) config('site.payments.verification_threshold', 50000)),
            '{affiliation_taux}' => (string) (int) config('affiliate.rate', 10),
            '{affiliation_jours_lien}' => (string) (int) config('affiliate.cookie_days', 30),
            '{affiliation_jours_dispo}' => (string) (int) config('affiliate.availability_days', 7),
            '{affiliation_minimum}' => Money::fcfa((int) config('affiliate.min_withdrawal', 2000)),
        ];
    }

    private static function rememberDefaults(): void
    {
        if (self::$application !== spl_object_id(app())) {
            self::$application = spl_object_id(app());
            self::$defaults = [];
        }

        foreach (['site', 'affiliate', 'live_sessions'] as $file) {
            self::$defaults[$file] ??= (array) config($file);
        }
    }
}
