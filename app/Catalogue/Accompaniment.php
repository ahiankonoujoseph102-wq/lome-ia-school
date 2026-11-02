<?php

namespace App\Catalogue;

use App\Models\SiteSetting;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * Novembre 2026, décision de Joseph : « accompagnement à vie » devient un accompagnement de 3 mois après la formation
 * (l'accès aux formations reste à vie). Appliqué aux valeurs de départ (config/site.php) et, une seule fois, aux
 * réglages déjà enregistrés dans l'admin (migration 2026_11_02_100004_switch_to_three_month_accompaniment), parce que
 * Joseph n'a pas le temps de les reprendre un par un.
 *
 * Textes connus (valeurs de départ) : remplacés en entier par une phrase reformulée. Tout autre texte (écrit à la
 * main) : seules les expressions « accompagnement à vie » sont remplacées. Les anciennes valeurs sont gardées dans le
 * réglage system.accompaniment_previous (jamais écrasé). Les offres d'accompagnement retirées ne sont pas touchées.
 * Retrait aussi de « 100 % pratique » et « orientées résultats » (interdits par Joseph).
 */
final class Accompaniment
{
    public const string LABEL = 'Accompagnement de 3 mois';

    public const string SAVED_KEY = 'system.accompaniment_previous';

    /**
     * Réglages jamais touchés (offres retirées, réglages techniques).
     *
     * @var list<string>
     */
    private const array SKIPPED = ['site.packs_page', 'site.packs_poster'];

    /**
     * Textes de départ connus => nouvelle phrase.
     *
     * @var array<string, string>
     */
    public const array REPLACEMENTS = [
        'Chaque formation achetée vous appartient : vous la revoyez autant que vous voulez et vous restez accompagné, même des années plus tard.' => 'Chaque formation achetée vous appartient : vous la revoyez autant que vous voulez, et vous êtes accompagné pendant 3 mois après la formation.',
        'Posez vos questions dans le groupe privé de la formation ou sur WhatsApp, aussi longtemps que vous en avez besoin.' => 'Posez vos questions dans le groupe privé de la formation ou sur WhatsApp pendant 3 mois après la formation.',
        'Chaque formation a son groupe de discussion réservé aux élèves : posez vos questions et avancez ensemble, sans limite de durée.' => 'Chaque formation a son groupe de discussion réservé aux élèves : posez vos questions et avancez ensemble, avec un accompagnement de 3 mois après la formation.',
        'Vos questions ont toujours une réponse, dans le groupe privé ou sur WhatsApp.' => 'Vos questions ont une réponse pendant 3 mois après la formation, dans le groupe privé ou sur WhatsApp.',
        'accès et accompagnement' => 'accès aux formations',
        'L\'accès à vie et l\'accompagnement à vie' => 'L\'accès à vie et 3 mois d\'accompagnement après la formation',
        'À vie. Une fois achetée, la formation reste dans votre espace élève : vous la revoyez autant que vous voulez. Vous bénéficiez aussi d\'un accompagnement à vie dans le groupe privé de la formation (tant que la plateforme est en activité).' => 'À vie. Une fois achetée, la formation reste dans votre espace élève : vous la revoyez autant que vous voulez. Vous bénéficiez aussi d\'un accompagnement de 3 mois après la formation, dans le groupe privé de la formation ou sur WhatsApp.',
        'À vie, avec un accompagnement à vie dans le groupe privé de la formation (tant que la plateforme est en activité).' => 'À vie pour la formation (tant que la plateforme est en activité), avec un accompagnement de 3 mois après la formation dans le groupe privé ou sur WhatsApp.',
        'Il accompagne également des entreprises et des porteurs de projets : sites web, applications, automatisations et conseil en IA. Avec Lomé IA School, il rend ces compétences accessibles à tous, avec un accompagnement à vie.' => 'Il accompagne également des entreprises et des porteurs de projets : sites web, applications, automatisations et conseil en IA. Avec Lomé IA School, il rend ces compétences accessibles à tous, avec un accompagnement de 3 mois après chaque formation.',
        'L\'accès à la formation est accordé à vie, avec un accompagnement à vie dans le groupe privé de la formation, tant que la plateforme Lomé IA School est en activité.' => 'L\'accès à la formation est accordé à vie, tant que la plateforme Lomé IA School est en activité. Un accompagnement de 3 mois après la formation est inclus, dans le groupe privé de la formation ou sur WhatsApp. Les élèves inscrits avant le 2 novembre 2026 gardent l\'accompagnement annoncé lors de leur achat.',
        '100 % pratique, en français' => 'Pratique et en français',
        'Des formations concrètes, orientées résultats, expliquées simplement et pensées pour notre réalité.' => 'Des formations concrètes, expliquées simplement et pensées pour notre réalité.',
    ];

    /**
     * Expressions remplacées dans les autres textes (écrits à la main), les plus longues d'abord.
     *
     * @var array<string, string>
     */
    public const array GENERIC = [
        'accès et accompagnement à vie' => 'accès à vie et accompagnement de 3 mois',
        'Accès et accompagnement à vie' => 'Accès à vie et accompagnement de 3 mois',
        'un accompagnement à vie' => 'un accompagnement de 3 mois après la formation',
        'Un accompagnement à vie' => 'Un accompagnement de 3 mois après la formation',
        'l\'accompagnement à vie' => 'l\'accompagnement de 3 mois après la formation',
        'L\'accompagnement à vie' => 'L\'accompagnement de 3 mois après la formation',
        'accompagnement à vie' => 'accompagnement de 3 mois',
        'Accompagnement à vie' => 'Accompagnement de 3 mois',
        'ACCOMPAGNEMENT À VIE' => 'ACCOMPAGNEMENT DE 3 MOIS',
    ];

    /**
     * Applique les remplacements à une valeur (textes dans des tableaux parcourus récursivement).
     */
    public static function rewrite(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::rewrite(...), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if (isset(self::REPLACEMENTS[$trimmed])) {
            return self::REPLACEMENTS[$trimmed];
        }

        return strtr($value, self::GENERIC);
    }

    /**
     * Met à jour les réglages enregistrés (une seule fois, sous verrou). Renvoie les clés modifiées.
     *
     * @return list<string>
     */
    public static function applyToStoredSettings(): array
    {
        $changed = [];

        DB::transaction(function () use (&$changed): void {
            if (SiteSetting::query()->where('key', self::SAVED_KEY)->lockForUpdate()->exists()) {
                return;
            }

            $previous = [];

            foreach (SiteSetting::query()->where('key', 'like', 'site.%')->lockForUpdate()->get() as $row) {
                if (in_array($row->key, self::SKIPPED, true)) {
                    continue;
                }

                $new = self::rewrite($row->value);

                if ($new !== $row->value) {
                    $previous[$row->key] = $row->value;
                    $row->value = $new;
                    $row->save();
                    $changed[] = $row->key;
                }
            }

            // Marque l'opération comme faite (et garde les anciennes valeurs), même si rien n'a changé.
            SiteSetting::query()->create(['key' => self::SAVED_KEY, 'value' => $previous]);
        });

        SiteSettings::flush();

        return $changed;
    }
}
