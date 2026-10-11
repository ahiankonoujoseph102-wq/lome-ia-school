<?php

namespace App\Packs;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Models\SiteSetting;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;

/**
 * Titre et sous-titre de la page /accompagnement validés lors de la refonte (octobre 2026).
 *
 * Pourquoi ils ne s'étaient pas appliqués : la page lit d'abord le réglage enregistré en base « site.packs_page » ;
 * la migration de la refonte (PackSalesTexts) ne retirait ce réglage que s'il était **exactement** l'un des deux
 * anciens jeux de textes complets (sinon : « modifié entre-temps, laissé tel quel »). Un réglage qui contenait encore
 * l'ancien titre et l'ancien sous-titre, mais qui différait sur un autre texte, ou qui a été remis après la migration
 * (commande de retour aux anciens textes), restait donc affiché. Et la balise <title> de la page était écrite en dur
 * (« Accompagnement IA ») dans la vue.
 *
 * Ici, seuls le titre et le sous-titre sont comparés, chacun à part : remplacés seulement s'ils valent encore
 * exactement un ancien texte connu (ancien texte d'origine ou texte de la commande app:pack-texts-apply). Un titre écrit
 * à la main n'est jamais remplacé. Les autres textes de la page, les offres, les prix, la date et les achats ne sont
 * jamais touchés. L'ancienne valeur est gardée une seule fois (réglage SAVED_KEY) et notée au journal. Relançable.
 */
class PackPageTitle
{
    public const string TITLE = 'Apprenez l\'intelligence artificielle avec Coach Joseph';

    public const string SUBTITLE = 'Trois niveaux pour apprendre à créer avec l\'IA, de vos premières affiches jusqu\'à votre propre projet, avec un accompagnement à vie.';

    public const string SAVED_KEY = 'system.packs_page_title_saved';

    /**
     * @return list<string> compte rendu
     */
    public static function apply(): array
    {
        $report = [];

        DB::transaction(function () use (&$report): void {
            $row = SiteSetting::query()->where('key', 'site.packs_page')->lockForUpdate()->first();

            if ($row === null || ! is_array($row->value)) {
                $report[] = 'Aucun texte enregistré : la page affiche déjà le titre et le sous-titre de la refonte.';

                return;
            }

            $value = $row->value;
            $changed = [];

            foreach (['title' => [self::TITLE, [PackTexts::OLD_PAGE['title'], PackTexts::NEW_PAGE['title']]], 'subtitle' => [self::SUBTITLE, [PackTexts::OLD_PAGE['subtitle'], PackTexts::NEW_PAGE['subtitle']]]] as $key => [$target, $known]) {
                $current = is_string($value[$key] ?? null) ? trim($value[$key]) : null;

                if ($current === $target) {
                    $report[] = ($key === 'title' ? 'Titre' : 'Sous-titre').' : déjà à jour.';
                } elseif ($current === null || in_array($current, $known, true)) {
                    $changed[$key] = $current;
                    $value[$key] = $target;
                    $report[] = ($key === 'title' ? 'Titre' : 'Sous-titre').' : remplacé (ancien texte enregistré).';
                } else {
                    $report[] = ($key === 'title' ? 'Titre' : 'Sous-titre').' : écrit à la main, laissé tel quel.';
                }
            }

            if ($changed === []) {
                return;
            }

            // Ancienne valeur gardée une seule fois (jamais écrasée par un passage suivant).
            if (! SiteSetting::query()->where('key', self::SAVED_KEY)->exists()) {
                SiteSetting::query()->create(['key' => self::SAVED_KEY, 'value' => $row->value]);
            }

            $row->value = $value;
            $row->save();
            SiteSettings::flush();

            ActivityLog::record(AdminAction::SettingsUpdated, 'Page Accompagnement : titre et sous-titre de la refonte appliqués (avant : « '.implode(' » / « ', array_filter($changed)).' »).');
        });

        return $report;
    }
}
