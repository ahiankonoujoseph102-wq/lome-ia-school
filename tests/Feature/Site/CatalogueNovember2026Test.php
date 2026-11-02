<?php

namespace Tests\Feature\Site;

use App\Catalogue\CatalogueUpdate;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Catalogue de novembre 2026 (App\Catalogue\CatalogueUpdate, migration 2026_11_02_100001_update_catalogue_november_2026) :
 * nouvelle formation complète, nouveaux prix (seul le prix change), formation gratuite et offres retirées du site,
 * « Après la formation » (attestation, stage, recrutement possible), présentiel au bureau, questions fréquentes.
 */
class CatalogueNovember2026Test extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_02_100001_update_catalogue_november_2026.php');
    }

    private function complete(): Formation
    {
        return Formation::query()->where('slug', CatalogueUpdate::COMPLETE_SLUG)->sole();
    }

    public function test_the_complete_formation_is_created_visible_featured_first_with_its_group(): void
    {
        $formation = $this->complete();

        $this->assertSame('Formation complète en intelligence artificielle, de A à Z', $formation->title);
        $this->assertSame(250000, $formation->price);
        $this->assertTrue($formation->is_active);
        $this->assertTrue($formation->is_featured);
        $this->assertSame('complete', $formation->category->value);
        $this->assertSame(CatalogueUpdate::complete()['careers'], $formation->careers);
        $this->assertTrue(ChatGroup::query()->where('formation_id', $formation->id)->exists());
        $this->assertSame($formation->id, Formation::query()->published()->first()?->id);

        // Aucune promesse interdite dans les textes.
        $text = mb_strtolower(json_encode(CatalogueUpdate::complete(), JSON_UNESCAPED_UNICODE));
        foreach (['garanti', '100 %', '100%', 'sans engagement', 'certifi', 'diplôme'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text, $forbidden);
        }
    }

    public function test_only_the_price_changes_and_past_purchases_keep_their_amount(): void
    {
        $formation = Formation::factory()->create(['slug' => 'automatisation-ia', 'title' => 'Titre réglé dans l\'admin', 'price' => 25500]);
        $purchase = Purchase::factory()->create(['formation_id' => $formation->id, 'amount' => 25500]);

        $this->migration()->up();

        $formation->refresh();
        $this->assertSame(55000, $formation->price);
        $this->assertSame('Titre réglé dans l\'admin', $formation->title);
        $this->assertSame(25500, $purchase->refresh()->amount);
    }

    public function test_it_can_run_again_without_duplicate(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, Formation::query()->where('slug', CatalogueUpdate::COMPLETE_SLUG)->count());
        $this->assertSame(1, ChatGroup::query()->where('formation_id', $this->complete()->id)->count());
    }

    public function test_the_free_formation_and_the_packs_are_hidden_and_their_links_lead_to_the_catalogue(): void
    {
        foreach (CatalogueUpdate::HIDDEN as $slug) {
            $this->assertFalse((bool) DB::table('formations')->where('slug', $slug)->value('is_active'), $slug);
            $this->get('/formations/'.$slug)->assertRedirect(route('formations.index'));
        }

        $this->get(route('packs'))->assertRedirect(route('formations.index'));
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="menu-packs"', false);
    }

    public function test_the_formation_page_shows_careers_after_training_in_person_and_its_questions(): void
    {
        $this->get(route('formations.show', $this->complete()))->assertOk()
            ->assertSee('data-test="formation-careers"', false)
            ->assertSee('Ingénieur en Prompt Engineering')
            ->assertSee('data-test="formation-after-training"', false)
            ->assertSee('Attestation de fin de formation')
            ->assertSee('Possibilité de stage de 3 mois')
            ->assertSee('Possibilité d\'être recruté par l\'école')
            ->assertSee('data-test="formation-in-person"', false)
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé, Togo')
            ->assertSee('Réserver une consultation gratuite')
            ->assertSee('Puis-je payer en ligne si je suis la formation au bureau ?')
            ->assertSee('Puis-je être recruté par l\'école ?')
            ->assertDontSee('{adresse}');
    }

    public function test_recruitment_is_only_offered_after_the_three_chosen_formations(): void
    {
        $other = Formation::factory()->create(['price' => 10000]);

        $this->get(route('formations.show', $other))->assertOk()
            ->assertSee('Attestation de fin de formation')
            ->assertSee('Possibilité de stage de 3 mois')
            ->assertDontSee('recruté', false);

        foreach (['creation-agents-ia', 'automatisation-ia'] as $slug) {
            $this->assertTrue(Formation::factory()->make(['slug' => $slug, 'price' => 1000])->offersRecruitment(), $slug);
        }

        $free = Formation::factory()->make(['slug' => 'automatisation-ia', 'price' => 0]);
        $this->assertFalse($free->offersRecruitment());
        $this->assertSame([], $free->afterTraining());
        $this->assertSame([], $free->extraFaq());
    }

    public function test_the_admin_edits_the_careers(): void
    {
        $admin = User::factory()->admin()->create();
        $formation = $this->complete();

        $this->actingAs($admin)->get(route('admin.formations.edit', $formation))->assertOk()->assertSee('Des métiers où ces compétences servent');

        $this->actingAs($admin)->put(route('admin.formations.update', $formation), [
            'title' => $formation->title,
            'description' => $formation->description,
            'price' => 250000,
            'category' => 'complete',
            'careers' => ['Métier A', '', 'Métier B'],
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Métier A', 'Métier B'], $formation->refresh()->careers);
    }
}
