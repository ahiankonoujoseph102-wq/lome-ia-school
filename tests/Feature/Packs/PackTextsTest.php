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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anciennes commandes « app:pack-texts-apply » et « app:pack-texts-revert » (avant la refonte de la page de vente,
 * remplacées par app:pack-sales-texts-*, voir PackSalesTextsTest) : toujours relançables sans effet indésirable,
 * jamais au-dessus d'un texte modifié dans l'admin. Ici, la base est remise dans son état d'avant la refonte.
 */
class PackTextsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Formation::query()->where('kind', 'pack')->update(['is_active' => true]);

        // État d'avant la refonte : descriptions de la création des offres, anciens textes fixes de la page.
        foreach (PackSetup::PACKS as $pack) {
            Formation::query()->where('slug', $pack['slug'])->update(['description' => $pack['description']]);
        }
        SiteSetting::query()->where('key', PackSalesTexts::SAVED_KEY)->delete();
        SiteSettings::save(['site.packs_page' => PackTexts::OLD_PAGE]);
    }

    private function pack(int $level): Formation
    {
        return Formation::query()->where('slug', 'accompagnement-ia-niveau-'.$level)->sole();
    }

    public function test_the_old_texts_are_the_ones_created_with_the_packs(): void
    {
        foreach ([1, 2, 3] as $level) {
            $this->assertSame(PackSetup::PACKS[$level - 1]['description'], $this->pack($level)->description);
        }

        $this->get(route('packs'))->assertOk()
            ->assertSee('Accompagnement IA')
            ->assertSee('Ce que chaque offre comprend')
            ->assertDontSee('Inclus à chaque niveau');
    }

    public function test_apply_replaces_descriptions_and_page_texts(): void
    {
        $prices = Formation::query()->where('kind', 'pack')->pluck('price', 'slug')->all();
        $titles = Formation::query()->where('kind', 'pack')->pluck('title', 'slug')->all();

        $this->artisan('app:pack-texts-apply')
            ->assertSuccessful()
            ->expectsOutputToContain('description remplacée')
            ->expectsOutputToContain('Textes fixes de la page : remplacés.');

        foreach (PackTexts::NEW_DESCRIPTIONS as $slug => $text) {
            $this->assertSame($text, Formation::query()->where('slug', $slug)->value('description'));
        }

        // Ni les prix ni les titres ne changent.
        $this->assertSame($prices, Formation::query()->where('kind', 'pack')->pluck('price', 'slug')->all());
        $this->assertSame($titles, Formation::query()->where('kind', 'pack')->pluck('title', 'slug')->all());
        $this->assertDatabaseHas('admin_activities', ['description' => 'Textes des offres d\'accompagnement : nouveaux textes appliqués (4 textes).']);

        $html = (string) $this->get(route('packs'))->assertOk()->getContent();
        $this->assertStringContainsString('Accompagnement IA avec Coach Joseph', $html);
        $this->assertStringContainsString('Inclus à chaque niveau', $html);
        $this->assertStringContainsString('Une attestation de participation', $html);
        // Les tirets deviennent des puces.
        $this->assertMatchesRegularExpression('#<li[^>]*>.*Des affiches professionnelles</span></li>#su', $html);
        $this->assertStringNotContainsString('- Des affiches professionnelles', $html);
    }

    public function test_apply_twice_changes_nothing_more(): void
    {
        $this->artisan('app:pack-texts-apply')->assertSuccessful();
        $updated = Formation::query()->where('kind', 'pack')->pluck('updated_at', 'slug')->all();
        $this->travel(5)->minutes();

        $this->artisan('app:pack-texts-apply')
            ->assertSuccessful()
            ->expectsOutputToContain('déjà à jour')
            ->doesntExpectOutputToContain('description remplacée');

        $this->assertEquals($updated, Formation::query()->where('kind', 'pack')->pluck('updated_at', 'slug')->all());
        $this->assertSame(1, AdminActivity::query()->where('description', 'like', 'Textes des offres%')->count());
    }

    public function test_revert_restores_the_old_texts_and_can_run_twice(): void
    {
        $this->artisan('app:pack-texts-apply')->assertSuccessful();
        $this->artisan('app:pack-texts-revert')->assertSuccessful()->expectsOutputToContain('description remplacée');

        foreach ([1, 2, 3] as $level) {
            $this->assertSame(PackSetup::PACKS[$level - 1]['description'], $this->pack($level)->description);
        }

        $this->assertSame(PackTexts::oldPage(), (array) SiteSettings::get('site.packs_page'));
        $this->get(route('packs'))->assertOk()->assertSee('Ce que chaque offre comprend')->assertDontSee('Inclus à chaque niveau');

        $this->artisan('app:pack-texts-revert')->assertSuccessful()->expectsOutputToContain('déjà à jour')->doesntExpectOutputToContain('description remplacée');
    }

    public function test_a_description_edited_in_the_admin_is_never_overwritten(): void
    {
        $this->pack(2)->update(['description' => 'Texte réécrit par Joseph dans l\'admin.']);

        $this->artisan('app:pack-texts-apply')->assertSuccessful()->expectsOutputToContain('modifiée dans l\'admin entre-temps, laissée telle quelle');
        $this->assertSame('Texte réécrit par Joseph dans l\'admin.', $this->pack(2)->description);
        $this->assertSame(PackTexts::NEW_DESCRIPTIONS['accompagnement-ia-niveau-1'], $this->pack(1)->description);

        $this->artisan('app:pack-texts-revert')->assertSuccessful();
        $this->assertSame('Texte réécrit par Joseph dans l\'admin.', $this->pack(2)->description);
    }

    public function test_line_endings_from_a_phone_do_not_block_the_change(): void
    {
        $this->pack(1)->update(['description' => str_replace("\n", "\r\n", PackSetup::PACKS[0]['description'])."\r\n"]);

        $this->artisan('app:pack-texts-apply')->assertSuccessful();

        $this->assertSame(PackTexts::NEW_DESCRIPTIONS['accompagnement-ia-niveau-1'], $this->pack(1)->description);
    }

    public function test_no_purchase_is_touched(): void
    {
        $purchase = Purchase::factory()->create(['user_id' => User::factory()->create()->id, 'formation_id' => $this->pack(3)->id, 'amount' => 75000]);
        $before = $purchase->fresh()?->toArray();

        $this->artisan('app:pack-texts-apply')->assertSuccessful();
        $this->artisan('app:pack-texts-revert')->assertSuccessful();

        $this->assertEquals($before, $purchase->fresh()?->toArray());
    }

    public function test_the_new_texts_respect_the_content_rules(): void
    {
        $all = mb_strtolower(implode("\n", PackTexts::NEW_DESCRIPTIONS)."\n".json_encode(PackTexts::NEW_PAGE, JSON_UNESCAPED_UNICODE));

        foreach (['certificat', 'diplôme', 'certifié', 'témoignage', 'garanti', 'revenus', 'gagner', 'par 10', 'places limitées', 'dernière chance', 'il ne reste', 'offre expire', 'vite', 'bonus'] as $word) {
            $this->assertStringNotContainsString($word, $all, $word);
        }

        $this->assertStringContainsString('attestation de participation', $all);
        // Les faits gardés : date du direct, lieux, formations annoncées.
        $this->assertStringContainsString('lundi 12 octobre 2026, en présentiel à lomé et en ligne', $all);
        $this->assertStringContainsString('notebooklm', $all);
    }
}
