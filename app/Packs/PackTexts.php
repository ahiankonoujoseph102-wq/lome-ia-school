<?php

namespace App\Packs;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Models\SiteSetting;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * Nouveaux textes des offres d'accompagnement (descriptions des trois offres et textes fixes de la page
 * /accompagnement), appliqués par Joseph quand il le décide :
 * « php artisan app:pack-texts-apply », et retirés par « php artisan app:pack-texts-revert ».
 *
 * Relançables sans effet indésirable : un texte n'est remplacé que s'il est exactement l'ancien texte
 * (appliquer) ou exactement le nouveau (revenir). Un texte modifié entre-temps dans l'admin n'est jamais
 * écrasé : il est signalé dans le compte rendu. Seules les descriptions changent : jamais un prix, un titre,
 * une liste de formations incluses ni un achat.
 */
class PackTexts
{
    /**
     * Nouvelles descriptions, par adresse d'offre. Faits repris des textes de Joseph, sans rien ajouter.
     *
     * @var array<string, string>
     */
    public const array NEW_DESCRIPTIONS = [
        'accompagnement-ia-niveau-1' => "Vous débutez avec l'intelligence artificielle : vous apprenez à créer des affiches et des vidéos professionnelles.\n"
            ."- Une initiation complète à l'intelligence artificielle\n"
            ."- Des affiches professionnelles\n"
            ."- Des vidéos publicitaires et des vidéos d'histoire\n"
            ."- La création d'une chaîne YouTube et ses conditions de monétisation\n"
            ."- NotebookLM, pour apprendre n'importe quelle compétence\n"
            .'Format : formations enregistrées, à suivre à votre rythme.',
        'accompagnement-ia-niveau-2' => "Vous voulez aller plus loin : tout le niveau 1, et en plus la création de sites web et d'applications avec l'intelligence artificielle, sans savoir coder.\n"
            ."- Des sites vitrines professionnels\n"
            ."- Des sites avec base de données\n"
            ."- Des applications\n"
            ."- Comment intégrer l'intelligence artificielle dans votre activité, et quels outils utiliser\n"
            .'Format : formations enregistrées, à suivre à votre rythme.',
        'accompagnement-ia-niveau-3' => "Vous voulez aller jusqu'au niveau avancé et réaliser votre propre projet, avec un accompagnement personnel de Coach Joseph : tout le contenu des niveaux 1 et 2, et en plus :\n"
            ."- La création d'agents IA\n"
            ."- L'automatisation\n"
            ."- La création de SaaS\n"
            ."- Un accompagnement personnel dans la réalisation de votre propre projet\n"
            .'Format : tous les enregistrements, et Coach Joseph refait la formation en direct pour votre groupe, à partir du lundi 12 octobre 2026, en présentiel à Lomé et en ligne.',
    ];

    /**
     * Nouveaux textes fixes de la page (mêmes clés que config('site.packs_page')).
     *
     * @var array<string, mixed>
     */
    public const array NEW_PAGE = [
        'eyebrow' => 'Offres d\'accompagnement',
        'title' => 'Accompagnement IA avec Coach Joseph',
        'subtitle' => 'Trois niveaux pour apprendre à utiliser l\'intelligence artificielle, de vos premières créations jusqu\'à votre propre projet. Chaque niveau comprend tout le précédent.',
        'common_eyebrow' => 'Dans toutes les offres',
        'common_title' => 'Inclus à chaque niveau',
        'common' => [
            ['icon' => 'document-text', 'title' => 'Un support pour chaque formation', 'text' => 'Un document, un résumé et un exercice.'],
            ['icon' => 'chat-bubble-left-right', 'title' => 'Le groupe VIP', 'text' => 'Un groupe de discussion réservé aux acheteurs des offres d\'accompagnement.'],
            ['icon' => 'heart', 'title' => 'Un accompagnement à vie', 'text' => 'Vos questions restent les bienvenues, sans limite de durée.'],
            ['icon' => 'academic-cap', 'title' => 'Une attestation de participation', 'text' => 'Remise à la fin de votre parcours.'],
        ],
        'payment_note' => 'Paiement par Mobile Money : envoyez le montant, puis la capture de votre transfert, comme pour toutes les formations du site.',
    ];

    /**
     * Textes fixes de la page à la création des offres (valeurs de départ qu'avait config/site.php avant la
     * refonte de la page de vente, voir PackSalesTexts ; le fichier contient maintenant les textes de la refonte).
     *
     * @var array<string, mixed>
     */
    public const array OLD_PAGE = [
        'eyebrow' => 'Offres d\'accompagnement',
        'title' => 'Accompagnement IA',
        'subtitle' => 'Trois niveaux qui réunissent plusieurs formations du site. Choisissez celui qui correspond à votre projet.',
        'common_eyebrow' => 'Dans toutes les offres',
        'common_title' => 'Ce que chaque offre comprend',
        'common' => [
            ['icon' => 'document-text', 'title' => 'Document, résumé et exercice', 'text' => 'Chaque formation est accompagnée d\'un document, d\'un résumé et d\'un exercice.'],
            ['icon' => 'chat-bubble-left-right', 'title' => 'Accès au groupe VIP', 'text' => ''],
            ['icon' => 'heart', 'title' => 'Accompagnement à vie', 'text' => ''],
            ['icon' => 'academic-cap', 'title' => 'Attestation de participation à la fin', 'text' => ''],
        ],
        'payment_note' => 'Paiement par Mobile Money : vous envoyez le montant, puis la capture de votre transfert, comme pour toutes les formations du site.',
    ];

    /**
     * Anciennes descriptions (celles créées avec les offres, octobre 2026), par adresse d'offre.
     *
     * @return array<string, string>
     */
    public static function oldDescriptions(): array
    {
        return collect(PackSetup::PACKS)->mapWithKeys(fn (array $pack): array => [$pack['slug'] => $pack['description']])->all();
    }

    /**
     * Anciens textes fixes de la page (à la création des offres).
     *
     * @return array<string, mixed>
     */
    public static function oldPage(): array
    {
        return self::OLD_PAGE;
    }

    /**
     * Applique les nouveaux textes. Compte rendu : une ligne par texte.
     *
     * @return list<string>
     */
    public static function apply(): array
    {
        return self::switch(self::oldDescriptions(), self::NEW_DESCRIPTIONS, self::oldPage(), self::NEW_PAGE, 'nouveaux textes appliqués');
    }

    /**
     * Revient aux anciens textes. Compte rendu : une ligne par texte.
     *
     * @return list<string>
     */
    public static function revert(): array
    {
        return self::switch(self::NEW_DESCRIPTIONS, self::oldDescriptions(), self::NEW_PAGE, self::oldPage(), 'anciens textes remis');
    }

    /**
     * @param  array<string, string>  $from
     * @param  array<string, string>  $to
     * @param  array<string, mixed>  $pageFrom
     * @param  array<string, mixed>  $pageTo
     * @return list<string>
     */
    private static function switch(array $from, array $to, array $pageFrom, array $pageTo, string $label): array
    {
        $report = [];
        $changed = 0;

        DB::transaction(function () use ($from, $to, $pageFrom, $pageTo, &$report, &$changed): void {
            foreach ($to as $slug => $target) {
                $row = DB::table('formations')->where('slug', $slug)->where('kind', 'pack')->lockForUpdate()->first(['id', 'title', 'description']);

                if ($row === null) {
                    $report[] = "{$slug} : offre introuvable, rien n'est changé.";

                    continue;
                }

                $current = self::normalize((string) $row->description);

                if ($current === self::normalize($target)) {
                    $report[] = "{$row->title} : déjà à jour, rien n'est changé.";
                } elseif ($current === self::normalize($from[$slug] ?? '')) {
                    // Seule la description change (jamais le prix, le titre ni les formations incluses).
                    DB::table('formations')->where('id', $row->id)->update(['description' => $target, 'updated_at' => now()]);
                    $report[] = "{$row->title} : description remplacée.";
                    $changed++;
                } else {
                    $report[] = "{$row->title} : description modifiée dans l'admin entre-temps, laissée telle quelle.";
                }
            }

            // Sans réglage enregistré, la page affiche les textes de la refonte (valeurs de départ de config/site.php) :
            // ce ne sont ni les anciens ni les nouveaux textes de cette commande, laissés tels quels.
            $page = SiteSetting::query()->where('key', 'site.packs_page')->value('value');

            if ($page == $pageTo) {
                $report[] = 'Textes fixes de la page : déjà à jour, rien n\'est changé.';
            } elseif ($page == $pageFrom) {
                SiteSettings::save(['site.packs_page' => $pageTo]);
                $report[] = 'Textes fixes de la page : remplacés.';
                $changed++;
            } else {
                $report[] = 'Textes fixes de la page : modifiés entre-temps, laissés tels quels.';
            }
        });

        if ($changed > 0) {
            ActivityLog::record(AdminAction::SettingsUpdated, 'Textes des offres d\'accompagnement : '.$label.' ('.$changed.' '.($changed > 1 ? 'textes' : 'texte').').');
        }

        return $report;
    }

    /**
     * Comparaison sans tenir compte des fins de ligne (téléphone, ordinateur) ni des espaces en bout de texte.
     */
    private static function normalize(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
