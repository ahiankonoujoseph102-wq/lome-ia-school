<?php

namespace Tests\Feature\Packs;

use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\SiteSetting;
use App\Models\User;
use App\Packs\PackSalesTexts;
use App\Packs\PackSetup;
use App\Packs\PackTexts;
use App\Settings\SiteSettings;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Textes de la page de vente /accompagnement (refonte) : appliqués par la migration et par
 * « app:pack-sales-texts-apply », retirés par « app:pack-sales-texts-revert ». Les anciens textes sont enregistrés
 * d'abord, une seule fois ; rien d'autre qu'une description ou un texte fixe n'est écrit ; un texte modifié dans
 * l'admin n'est jamais remplacé sans --force.
 */
class PackSalesTextsTest extends TestCase
{
    use RefreshDatabase;

    private function pack(int $level): Formation
    {
        return Formation::query()->where('slug', 'accompagnement-ia-niveau-'.$level)->sole();
    }

    /**
     * Remet la base dans l'état d'avant la refonte (descriptions de la création, aucun texte enregistré).
     */
    private function beforeRedesign(): void
    {
        foreach (PackSetup::PACKS as $pack) {
            Formation::query()->where('slug', $pack['slug'])->update(['description' => $pack['description']]);
        }
        SiteSetting::query()->whereIn('key', [PackSalesTexts::SAVED_KEY, 'site.packs_page'])->delete();
        SiteSettings::flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'packs' => Formation::query()->where('kind', 'pack')->orderBy('id')->get(['id', 'title', 'price', 'is_active', 'registration_ends_at', 'image', 'sort_order'])->toArray(),
            'items' => DB::table('formation_pack_items')->orderBy('id')->get()->toArray(),
            'courses' => Formation::query()->where('kind', '!=', 'pack')->orderBy('id')->get(['id', 'title', 'description', 'price'])->toArray(),
        ];
    }

    public function test_the_migration_applies_the_new_texts_and_saves_the_old_ones(): void
    {
        foreach (PackSalesTexts::DESCRIPTIONS as $slug => $text) {
            $this->assertSame($text, Formation::query()->where('slug', $slug)->value('description'));
        }

        $saved = PackSalesTexts::saved();
        foreach (PackSetup::PACKS as $pack) {
            $this->assertSame($pack['description'], $saved['descriptions'][$pack['slug']]);
        }

        // Textes fixes : les valeurs de départ (nouveaux textes), aucun réglage enregistré.
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.packs_page']);
        $this->assertSame('Comment s\'inscrire', config('site.packs_page.steps_title'));
    }

    public function test_apply_saves_the_old_texts_first_then_replaces_only_the_descriptions(): void
    {
        $this->beforeRedesign();
        $before = $this->snapshot();
        $logged = AdminActivity::query()->where('description', 'like', 'Page de vente%')->count();

        $this->artisan('app:pack-sales-texts-apply')
            ->assertSuccessful()
            ->expectsOutputToContain('description remplacée (ancien texte enregistré)');

        $this->assertSame(PackSalesTexts::DESCRIPTIONS['accompagnement-ia-niveau-3'], $this->pack(3)->description);
        $this->assertSame(PackSetup::PACKS[2]['description'], PackSalesTexts::saved()['descriptions']['accompagnement-ia-niveau-3']);
        // Prix, titres, dates, formations incluses et formations habituelles : rien ne bouge.
        $this->assertEquals($before, $this->snapshot());
        $this->assertSame($logged + 1, AdminActivity::query()->where('description', 'like', 'Page de vente de l\'accompagnement : nouveaux textes appliqués%')->count());
    }

    public function test_apply_twice_changes_nothing_and_never_overwrites_the_saved_texts(): void
    {
        $logged = AdminActivity::query()->where('description', 'like', 'Page de vente%')->count();
        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful()->expectsOutputToContain('déjà à jour')->doesntExpectOutputToContain('remplacée');
        $saved = PackSalesTexts::saved();

        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful()->doesntExpectOutputToContain('remplacée');
        $this->assertSame($saved, PackSalesTexts::saved());
        $this->assertSame($logged, AdminActivity::query()->where('description', 'like', 'Page de vente%')->count());
    }

    public function test_texts_from_the_previous_apply_command_are_replaced_too(): void
    {
        $this->beforeRedesign();
        foreach (PackTexts::NEW_DESCRIPTIONS as $slug => $text) {
            Formation::query()->where('slug', $slug)->update(['description' => $text]);
        }
        SiteSettings::save(['site.packs_page' => PackTexts::NEW_PAGE]);

        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful()->expectsOutputToContain('Textes fixes de la page : remplacés');

        $this->assertSame(PackSalesTexts::DESCRIPTIONS['accompagnement-ia-niveau-1'], $this->pack(1)->description);
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.packs_page']);
        $this->assertSame(PackTexts::NEW_PAGE, PackSalesTexts::saved()['page']);
        $this->assertSame(PackTexts::NEW_DESCRIPTIONS['accompagnement-ia-niveau-1'], PackSalesTexts::saved()['descriptions']['accompagnement-ia-niveau-1']);

        // Retour : exactement les textes d'avant (ceux de l'ancienne commande).
        $this->artisan('app:pack-sales-texts-revert')->assertSuccessful();
        $this->assertSame(PackTexts::NEW_DESCRIPTIONS['accompagnement-ia-niveau-1'], $this->pack(1)->description);
        $this->assertSame(PackTexts::NEW_PAGE, SiteSettings::get('site.packs_page'));
    }

    public function test_revert_brings_back_the_old_texts_and_can_run_twice(): void
    {
        $this->artisan('app:pack-sales-texts-revert')->assertSuccessful()->expectsOutputToContain('ancien texte remis');

        foreach (PackSetup::PACKS as $pack) {
            $this->assertSame($pack['description'], Formation::query()->where('slug', $pack['slug'])->value('description'));
        }
        $this->assertSame(PackTexts::OLD_PAGE, SiteSettings::get('site.packs_page'));

        // Page lisible avec les anciens textes (clés nouvelles prises dans les valeurs de départ).
        Formation::query()->where('kind', 'pack')->update(['is_active' => true, 'registration_ends_at' => null]);
        $this->get(route('packs'))->assertOk()->assertSee('Ce que chaque offre comprend')->assertSee('Comment s&#039;inscrire', false);

        $this->artisan('app:pack-sales-texts-revert')->assertSuccessful()->expectsOutputToContain('déjà en place')->doesntExpectOutputToContain('remis.');

        // Et de nouveau les nouveaux textes.
        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful();
        $this->assertSame(PackSalesTexts::DESCRIPTIONS['accompagnement-ia-niveau-2'], $this->pack(2)->description);
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.packs_page']);
    }

    public function test_a_description_edited_in_the_admin_is_left_alone_unless_forced(): void
    {
        $this->beforeRedesign();
        $this->pack(2)->update(['description' => 'Texte réécrit par Joseph.']);

        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful()->expectsOutputToContain('description modifiée dans l\'admin, laissée telle quelle');
        $this->assertSame('Texte réécrit par Joseph.', $this->pack(2)->description);
        $this->assertArrayNotHasKey('accompagnement-ia-niveau-2', PackSalesTexts::saved()['descriptions']);

        $this->artisan('app:pack-sales-texts-apply', ['--force' => true])->assertSuccessful();
        $this->assertSame(PackSalesTexts::DESCRIPTIONS['accompagnement-ia-niveau-2'], $this->pack(2)->description);
        $this->assertSame('Texte réécrit par Joseph.', PackSalesTexts::saved()['descriptions']['accompagnement-ia-niveau-2']);

        $this->artisan('app:pack-sales-texts-revert')->assertSuccessful();
        $this->assertSame('Texte réécrit par Joseph.', $this->pack(2)->description);

        // Modifiée après la refonte : le retour ne l'écrase pas.
        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful();
        $this->pack(1)->update(['description' => 'Nouvelle version de Joseph.']);
        $this->artisan('app:pack-sales-texts-revert')->assertSuccessful()->expectsOutputToContain('modifiée dans l\'admin depuis');
        $this->assertSame('Nouvelle version de Joseph.', $this->pack(1)->description);
    }

    public function test_line_endings_from_a_phone_do_not_block_the_change(): void
    {
        $this->beforeRedesign();
        $this->pack(1)->update(['description' => str_replace("\n", "\r\n", PackSetup::PACKS[0]['description'])."\r\n"]);

        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful();

        $this->assertSame(PackSalesTexts::DESCRIPTIONS['accompagnement-ia-niveau-1'], $this->pack(1)->description);
    }

    public function test_no_purchase_is_touched(): void
    {
        $purchase = Purchase::factory()->create(['user_id' => User::factory()->create()->id, 'formation_id' => $this->pack(3)->id, 'amount' => 75000]);
        $before = $purchase->fresh()?->toArray();

        $this->artisan('app:pack-sales-texts-revert')->assertSuccessful();
        $this->artisan('app:pack-sales-texts-apply')->assertSuccessful();

        $this->assertEquals($before, $purchase->fresh()?->toArray());
    }

    public function test_each_offer_has_one_hook_and_at_most_four_highlights(): void
    {
        foreach (PackSalesTexts::DESCRIPTIONS as $text) {
            $summary = RichText::summary($text);
            $this->assertCount(1, $summary['hook']);
            $this->assertNotEmpty($summary['points']);
            $this->assertLessThanOrEqual(4, count($summary['points']));
            $this->assertSame([], $summary['rest']);
        }
    }

    public function test_the_new_texts_respect_the_content_rules(): void
    {
        $all = mb_strtolower(implode("\n", PackSalesTexts::DESCRIPTIONS)."\n".json_encode(config('site.packs_page'), JSON_UNESCAPED_UNICODE));

        foreach (['certificat', 'diplôme', 'certifié', 'témoignage', 'avis', 'garanti', 'revenus', 'gagner', 'par 10', 'productivité', 'places limitées', 'dernière chance', 'il ne reste', 'offre expire', 'vite', 'bonus', 'etc.', 'tél.', 'n°'] as $word) {
            $this->assertStringNotContainsString($word, $all, $word);
        }

        $this->assertStringContainsString('attestation de participation', $all);
        $this->assertStringContainsString('lundi 12 octobre 2026, en présentiel à lomé et en ligne', $all);
        // Contenus annoncés sans formation correspondante dans les offres : laissés hors des points forts.
        foreach (['notebooklm', 'monétisation', 'chaîne youtube', 'intégrer l\'intelligence artificielle dans votre activité'] as $content) {
            $this->assertStringNotContainsString($content, $all, $content);
        }
    }
}
