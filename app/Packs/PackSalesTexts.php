<?php

namespace App\Packs;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Models\SiteSetting;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * Textes de la page de vente /accompagnement (refonte d'octobre 2026) : descriptions courtes des trois offres
 * (une phrase d'accroche, puis quatre points forts au plus, écrits en lignes à tiret) et textes fixes de la page.
 *
 * - Appliqués au déploiement par la migration 2026_10_23_100000_apply_pack_sales_texts, et à la demande par
 *   « php artisan app:pack-sales-texts-apply » (relançable, option --force pour remplacer aussi un texte modifié).
 * - Retirés par « php artisan app:pack-sales-texts-revert » : les textes d'avant reviennent.
 *
 * Les textes d'avant sont **enregistrés d'abord** (réglage SAVED_KEY), une seule fois : jamais écrasés par un
 * nouveau passage. Seule la colonne « description » des offres et le réglage « site.packs_page » sont écrits :
 * jamais un prix, un titre, une date de fin des inscriptions, une liste de formations incluses ni un achat.
 * Un texte modifié dans l'admin entre-temps n'est pas remplacé (signalé dans le compte rendu).
 */
class PackSalesTexts
{
    /** Réglage qui garde les textes d'avant : {descriptions: {adresse: texte}, page: valeur enregistrée ou null}. */
    public const string SAVED_KEY = 'system.pack_sales_texts_saved';

    /**
     * Nouvelles descriptions, par adresse d'offre. Faits repris des textes de Joseph, sans rien ajouter.
     * Première ligne = accroche ; lignes à tiret = points forts (quatre au plus, affichés sur la carte).
     *
     * @var array<string, string>
     */
    public const array DESCRIPTIONS = [
        'accompagnement-ia-niveau-1' => "Vous débutez ? Apprenez à créer des affiches et des vidéos professionnelles avec l'intelligence artificielle, à votre rythme.\n"
            ."- Prendre en main ChatGPT et Claude\n"
            ."- Créer des affiches professionnelles\n"
            ."- Créer des vidéos publicitaires\n"
            ."- Créer des vidéos d'histoire",
        'accompagnement-ia-niveau-2' => "Tout le niveau 1, et en plus : créez des sites web et des applications avec l'intelligence artificielle, sans savoir coder.\n"
            ."- Créer des sites vitrines professionnels\n"
            ."- Créer des sites web avec base de données\n"
            .'- Créer des applications et des logiciels',
        'accompagnement-ia-niveau-3' => "Tout le contenu des niveaux 1 et 2, et en plus : allez jusqu'au niveau avancé et réalisez votre propre projet, avec Coach Joseph à vos côtés.\n"
            ."- Agents IA, automatisation et création de SaaS\n"
            ."- La formation refaite en direct par Coach Joseph pour votre groupe, à partir du lundi 12 octobre 2026, en présentiel à Lomé et en ligne\n"
            ."- Un accompagnement personnel pour réaliser votre propre projet\n"
            .'- Tous les enregistrements, à revoir quand vous le souhaitez',
    ];

    /**
     * Textes fixes de la page d'avant cette refonte, s'il n'y a rien d'enregistré en base : valeurs de départ
     * qu'avait config/site.php (gardées ici, le fichier contient maintenant les nouveaux textes).
     *
     * @var array<string, mixed>
     */
    public const array PREVIOUS_PAGE = PackTexts::OLD_PAGE;

    /**
     * Applique les nouveaux textes. $force : remplace aussi une description modifiée dans l'admin
     * (elle est enregistrée d'abord, comme les autres).
     *
     * @return list<string> compte rendu, une ligne par texte
     */
    public static function apply(bool $force = false): array
    {
        $report = [];
        $changed = 0;

        DB::transaction(function () use ($force, &$report, &$changed): void {
            $saved = self::saved();
            $known = self::knownPreviousDescriptions();

            foreach (self::DESCRIPTIONS as $slug => $target) {
                $row = self::pack($slug);

                if ($row === null) {
                    $report[] = "{$slug} : offre introuvable, rien n'est changé.";

                    continue;
                }

                $current = self::normalize($row['description']);

                if ($current === self::normalize($target)) {
                    $report[] = "{$row['title']} : déjà à jour.";

                    continue;
                }

                $isKnown = in_array($current, $known[$slug] ?? [], true)
                    || (isset($saved['descriptions'][$slug]) && $current === self::normalize($saved['descriptions'][$slug]));

                if (! $isKnown && ! $force) {
                    $report[] = "{$row['title']} : description modifiée dans l'admin, laissée telle quelle (option --force pour la remplacer quand même).";

                    continue;
                }

                // L'ancien texte est enregistré d'abord, une seule fois (jamais écrasé par un passage suivant).
                $saved['descriptions'][$slug] ??= $row['description'];
                self::storeSaved($saved);

                DB::table('formations')->where('id', $row['id'])->update(['description' => $target, 'updated_at' => now()]);
                $report[] = "{$row['title']} : description remplacée (ancien texte enregistré).";
                $changed++;
            }

            // Textes fixes : les nouveaux sont les valeurs de départ de config/site.php ; un réglage enregistré
            // (anciens textes) est retiré après avoir été enregistré.
            $stored = SiteSetting::query()->where('key', 'site.packs_page')->first();

            if ($stored === null) {
                $report[] = 'Textes fixes de la page : déjà à jour.';
            } elseif (in_array($stored->value, [PackTexts::OLD_PAGE, PackTexts::NEW_PAGE], false) || $force || $stored->value == ($saved['page'] ?? null)) {
                if (! array_key_exists('page', $saved)) {
                    $saved['page'] = $stored->value;
                    self::storeSaved($saved);
                }

                $stored->delete();
                $report[] = 'Textes fixes de la page : remplacés (anciens textes enregistrés).';
                $changed++;
            } else {
                $report[] = 'Textes fixes de la page : modifiés entre-temps, laissés tels quels (option --force pour les remplacer).';
            }

            SiteSettings::flush();
        });

        if ($changed > 0) {
            ActivityLog::record(AdminAction::SettingsUpdated, 'Page de vente de l\'accompagnement : nouveaux textes appliqués ('.$changed.' '.($changed > 1 ? 'textes' : 'texte').').');
        }

        return $report;
    }

    /**
     * Remet les textes d'avant (ceux enregistrés par apply). Un texte modifié depuis n'est pas touché.
     *
     * @return list<string>
     */
    public static function revert(): array
    {
        $report = [];
        $changed = 0;

        DB::transaction(function () use (&$report, &$changed): void {
            $saved = self::saved();

            foreach (self::DESCRIPTIONS as $slug => $target) {
                $row = self::pack($slug);

                if ($row === null) {
                    $report[] = "{$slug} : offre introuvable, rien n'est changé.";

                    continue;
                }

                $previous = $saved['descriptions'][$slug] ?? null;

                if ($previous === null) {
                    $report[] = "{$row['title']} : aucun ancien texte enregistré, rien n'est changé.";
                } elseif (self::normalize($row['description']) === self::normalize($previous)) {
                    $report[] = "{$row['title']} : ancien texte déjà en place.";
                } elseif (self::normalize($row['description']) === self::normalize($target)) {
                    DB::table('formations')->where('id', $row['id'])->update(['description' => $previous, 'updated_at' => now()]);
                    $report[] = "{$row['title']} : ancien texte remis.";
                    $changed++;
                } else {
                    $report[] = "{$row['title']} : description modifiée dans l'admin depuis, laissée telle quelle.";
                }
            }

            $stored = SiteSetting::query()->where('key', 'site.packs_page')->first();
            $previousPage = array_key_exists('page', $saved) ? $saved['page'] : self::PREVIOUS_PAGE;

            if ($stored !== null && $stored->value == $previousPage) {
                $report[] = 'Textes fixes de la page : anciens textes déjà en place.';
            } elseif ($stored === null) {
                // Pas de réglage = nouveaux textes (valeurs de départ) : les anciens sont enregistrés à la place.
                SiteSettings::save(['site.packs_page' => $previousPage ?? self::PREVIOUS_PAGE]);
                $report[] = 'Textes fixes de la page : anciens textes remis.';
                $changed++;
            } else {
                $report[] = 'Textes fixes de la page : modifiés depuis, laissés tels quels.';
            }

            SiteSettings::flush();
        });

        if ($changed > 0) {
            ActivityLog::record(AdminAction::SettingsUpdated, 'Page de vente de l\'accompagnement : anciens textes remis ('.$changed.' '.($changed > 1 ? 'textes' : 'texte').').');
        }

        return $report;
    }

    /**
     * Textes d'avant enregistrés.
     *
     * @return array{descriptions?: array<string, string>, page?: mixed}
     */
    public static function saved(): array
    {
        $value = SiteSetting::query()->where('key', self::SAVED_KEY)->value('value');

        return is_array($value) ? $value : [];
    }

    /**
     * Versions connues des descriptions d'avant (créées avec les offres, puis nouveaux textes d'octobre 2026) :
     * remplacées sans --force.
     *
     * @return array<string, list<string>>
     */
    private static function knownPreviousDescriptions(): array
    {
        $known = [];

        foreach (PackTexts::oldDescriptions() as $slug => $text) {
            $known[$slug][] = self::normalize($text);
        }

        foreach (PackTexts::NEW_DESCRIPTIONS as $slug => $text) {
            $known[$slug][] = self::normalize($text);
        }

        return $known;
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    private static function storeSaved(array $saved): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::SAVED_KEY], ['value' => $saved]);
    }

    /**
     * Offre verrouillée pendant le changement, ou null si elle n'existe pas.
     *
     * @return array{id: int, title: string, description: string}|null
     */
    private static function pack(string $slug): ?array
    {
        $row = DB::table('formations')->where('slug', $slug)->where('kind', 'pack')->lockForUpdate()->first(['id', 'title', 'description']);

        return $row === null ? null : ['id' => (int) $row->id, 'title' => (string) $row->title, 'description' => (string) $row->description];
    }

    /**
     * Comparaison sans tenir compte des fins de ligne (téléphone, ordinateur) ni des espaces en bout de texte.
     */
    private static function normalize(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
