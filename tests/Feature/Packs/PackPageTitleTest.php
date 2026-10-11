<?php

namespace Tests\Feature\Packs;

use App\Models\Formation;
use App\Models\SiteSetting;
use App\Packs\PackPageTitle;
use App\Packs\PackTexts;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Titre et sous-titre de la page /accompagnement validés lors de la refonte : appliqués même quand le réglage
 * enregistré en base contient encore l'ancien titre et l'ancien sous-titre avec un autre texte différent (cas que la
 * migration de la refonte laissait tel quel). Seules ces deux clés changent ; un titre écrit à la main est gardé.
 */
class PackPageTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Formation::query()->where('kind', 'pack')->update(['is_active' => true, 'registration_ends_at' => now()->addDays(2)]);
    }

    public function test_the_default_page_shows_the_validated_title_in_the_heading_and_the_tab(): void
    {
        $this->get(route('packs'))->assertOk()
            ->assertSee('<title>'.e(PackPageTitle::TITLE), false)
            ->assertSee(e(PackPageTitle::SUBTITLE), false);
        $this->assertSame(PackPageTitle::TITLE, config('site.packs_page.title'));
        $this->assertSame(PackPageTitle::SUBTITLE, config('site.packs_page.subtitle'));
    }

    public function test_old_title_and_subtitle_left_in_the_database_are_replaced_and_nothing_else(): void
    {
        // Anciens textes, avec une autre différence (ce qui empêchait la migration de la refonte de les retirer).
        $stored = [...PackTexts::OLD_PAGE, 'common_title' => 'Ce que chaque offre comprend !'];
        SiteSettings::save(['site.packs_page' => $stored]);
        $this->get(route('packs'))->assertSee('Accompagnement IA')->assertSee('réunissent plusieurs formations');

        $report = PackPageTitle::apply();

        $this->assertContains('Titre : remplacé (ancien texte enregistré).', $report);
        $value = SiteSetting::query()->where('key', 'site.packs_page')->value('value');
        $this->assertSame(PackPageTitle::TITLE, $value['title']);
        $this->assertSame(PackPageTitle::SUBTITLE, $value['subtitle']);
        // Le reste est gardé tel quel.
        $this->assertSame('Ce que chaque offre comprend !', $value['common_title']);
        $this->assertSame(PackTexts::OLD_PAGE['common'], $value['common']);
        // Ancienne valeur gardée une seule fois, et notée au journal.
        $this->assertSame($stored, SiteSetting::query()->where('key', PackPageTitle::SAVED_KEY)->value('value'));
        $this->assertDatabaseHas('admin_activities', ['action' => 'settings_updated']);

        $this->get(route('packs'))->assertOk()
            ->assertSee(e(PackPageTitle::TITLE), false)
            ->assertDontSee('réunissent plusieurs formations');

        // Relançable sans effet.
        $this->assertSame(['Titre : déjà à jour.', 'Sous-titre : déjà à jour.'], PackPageTitle::apply());
        $this->assertSame($stored, SiteSetting::query()->where('key', PackPageTitle::SAVED_KEY)->value('value'));
    }

    public function test_the_texts_of_the_old_command_are_replaced_too(): void
    {
        SiteSettings::save(['site.packs_page' => PackTexts::NEW_PAGE]);

        PackPageTitle::apply();

        $this->assertSame(PackPageTitle::TITLE, config('site.packs_page.title'));
        $this->assertSame(PackPageTitle::SUBTITLE, config('site.packs_page.subtitle'));
    }

    public function test_a_hand_written_title_is_kept(): void
    {
        SiteSettings::save(['site.packs_page' => [...PackTexts::OLD_PAGE, 'title' => 'Mon titre à moi']]);

        $report = PackPageTitle::apply();

        $this->assertContains('Titre : écrit à la main, laissé tel quel.', $report);
        $this->assertSame('Mon titre à moi', config('site.packs_page.title'));
        $this->assertSame(PackPageTitle::SUBTITLE, config('site.packs_page.subtitle'));
    }

    public function test_nothing_is_written_without_a_stored_setting(): void
    {
        $this->assertSame(['Aucun texte enregistré : la page affiche déjà le titre et le sous-titre de la refonte.'], PackPageTitle::apply());
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.packs_page']);
        $this->assertDatabaseMissing('site_settings', ['key' => PackPageTitle::SAVED_KEY]);
    }

    public function test_the_migration_never_touches_prices_or_offer_texts(): void
    {
        SiteSettings::save(['site.packs_page' => PackTexts::OLD_PAGE]);
        $before = Formation::query()->where('kind', 'pack')->orderBy('id')->get(['price', 'title', 'description', 'registration_ends_at'])->toArray();

        $migration = require database_path('migrations/2026_10_24_100000_apply_packs_page_title.php');
        $migration->up();
        $migration->up();

        $this->assertSame($before, Formation::query()->where('kind', 'pack')->orderBy('id')->get(['price', 'title', 'description', 'registration_ends_at'])->toArray());
        $this->assertSame(PackPageTitle::TITLE, config('site.packs_page.title'));
    }
}
