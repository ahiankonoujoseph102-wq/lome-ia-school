<?php

namespace Tests\Feature\FreeRegistrations;

use App\Assistant\AmaKnowledge;
use App\Assistant\AmaPrompt;
use App\Enums\FreeOptionMode;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Formation gratuite du 10 octobre 2026 créée par migration, en brouillon (masquée), avec ses 4 options.
 * Et rien ne change pour les formations payantes.
 */
class FreeFormationMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_13_100001_add_free_ai_formation.php');
    }

    public function test_the_free_formation_is_created_as_a_hidden_draft_with_its_four_options(): void
    {
        $formation = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();

        $this->assertSame('Formation gratuite : utiliser l\'IA au quotidien', $formation->title);
        $this->assertTrue($formation->isFree());
        $this->assertFalse($formation->is_active);
        $this->assertSame('2026-10-10', $formation->event_date->toDateString());
        $this->assertSame('Samedi 10 octobre 2026', $formation->eventDateLabel());
        $this->assertSame('Adidogomé, Lycée moderne 2, Lomé', $formation->event_location);
        $this->assertNotNull(ChatGroup::query()->where('formation_id', $formation->id)->first());

        $options = $formation->freeOptions()->get();
        $this->assertSame(['Présentiel journée', 'Présentiel soir', 'En ligne journée', 'En ligne soir'], $options->pluck('label')->all());
        $this->assertSame([20, 20, 50, 50], $options->pluck('capacity')->all());
        $this->assertSame([FreeOptionMode::Onsite, FreeOptionMode::Onsite, FreeOptionMode::Online, FreeOptionMode::Online], $options->pluck('mode')->all());
        $this->assertSame('9 h à 16 h (pause de 13 h à 14 h)', $options[0]->schedule);

        // Depuis octobre 2026, une migration suivante la marque « terminée » ; avant cela, elle proposait ses options.
        $this->assertTrue($formation->isFreeEnded());
        $formation->forceFill(['free_ended' => false])->save();

        // Masquée : les visiteurs ne la voient pas ; l'admin peut la relire.
        $this->get('/formations/formation-gratuite-ia')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/formations/formation-gratuite-ia')->assertOk()->assertSee('Présentiel soir');
    }

    public function test_its_texts_contain_no_forbidden_promise(): void
    {
        $formation = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        $text = mb_strtolower(implode(' ', [$formation->title, $formation->subtitle, $formation->description, ...$formation->outcomes, ...$formation->audience]));

        foreach (['certificat', 'diplôme', 'certifié', 'garanti', 'revenu', 'places limitées', 'dernière', 'vite !', 'bonus'] as $word) {
            $this->assertStringNotContainsString($word, $text, "« $word » ne doit pas apparaître.");
        }
    }

    public function test_running_the_migration_again_changes_nothing(): void
    {
        $formation = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        $formation->forceFill(['title' => 'Titre modifié dans l\'admin'])->save();

        $this->migration()->up();

        $this->assertSame(1, DB::table('formations')->where('slug', 'formation-gratuite-ia')->count());
        $this->assertSame('Titre modifié dans l\'admin', $formation->fresh()->title);
        $this->assertSame(4, $formation->freeOptions()->count());
    }

    public function test_a_paid_formation_page_and_checkout_are_unchanged(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('Acheter maintenant')
            ->assertSee("25\u{00A0}000\u{00A0}FCFA", false)
            ->assertSee('"price":25000', false)
            ->assertDontSee('Je m\'inscris gratuitement', false)
            ->assertDontSee('data-test="free-registration"', false);

        $this->actingAs(User::factory()->create())->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('Étape')
            ->assertDontSee('Je m\'inscris gratuitement', false);

        $this->assertSame('25 000 FCFA', str_replace("\u{00A0}", ' ', $formation->formatted_price));
    }

    public function test_ama_knows_the_free_formation_and_the_cheapest_paid_one_stays_the_reference(): void
    {
        // Formation avant qu'elle soit marquée « terminée » (migration suivante) : inscriptions ouvertes.
        Formation::query()->where('slug', 'formation-gratuite-ia')->update(['is_active' => true, 'free_ended' => false]);
        Formation::factory()->create(['title' => 'Formation payante exemple', 'price' => 5100]);

        $formations = AmaKnowledge::formations();
        $this->assertStringContainsString('FORMATION GRATUITE, sans aucun paiement, le samedi 10 octobre 2026', $formations);
        $this->assertStringContainsString('Présentiel journée (9 h à 16 h (pause de 13 h à 14 h)), présentiel, 20 places restantes', $formations);
        $this->assertStringContainsString('Le lien Google Meet est publié dans le groupe des inscrits', $formations);

        $prompt = AmaPrompt::build([]);
        $this->assertStringContainsString('Formations à partir de 5 100 FCFA', str_replace("\u{00A0}", ' ', $prompt));
    }
}
