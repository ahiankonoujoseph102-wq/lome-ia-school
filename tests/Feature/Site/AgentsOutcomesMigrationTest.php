<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use Database\Seeders\FormationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migration 2026_10_08_100000_update_agents_ia_outcomes : « Ce que vous allez apprendre » de « Agents IA » suit
 * le programme des 7 soirs. Seule la liste d'origine exacte est remplacée ; une liste modifiée dans l'admin reste intacte.
 */
class AgentsOutcomesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_08_100000_update_agents_ia_outcomes.php');
    }

    public function test_the_original_list_follows_the_seven_evenings_without_email(): void
    {
        $migration = $this->migration();
        $formation = Formation::factory()->create(['slug' => 'creation-agents-ia', 'outcomes' => $migration::OLD_OUTCOMES]);
        $other = Formation::factory()->create(['slug' => 'automatisation-ia', 'outcomes' => $migration::OLD_OUTCOMES]);

        $migration->up();
        $migration->up();

        $outcomes = $formation->fresh()->outcomes;
        $this->assertSame($migration::NEW_OUTCOMES, $outcomes);
        $this->assertCount(7, $outcomes);
        $this->assertStringNotContainsString('e-mail', mb_strtolower(implode(' ', $outcomes)));
        $this->assertSame($migration::OLD_OUTCOMES, $other->fresh()->outcomes);

        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('Créer un agent avec Gemini (Gems) et lui donner des documents')
            ->assertDontSee('e-mail, documents');
    }

    public function test_a_list_edited_in_the_admin_is_never_touched(): void
    {
        $edited = ['Une liste écrite par Joseph'];
        $formation = Formation::factory()->create(['slug' => 'creation-agents-ia', 'outcomes' => $edited]);

        $this->migration()->up();

        $this->assertSame($edited, $formation->fresh()->outcomes);
    }

    public function test_the_seeder_starts_with_the_same_list(): void
    {
        $this->seed(FormationSeeder::class);

        $this->assertSame($this->migration()::NEW_OUTCOMES, Formation::query()->where('slug', 'creation-agents-ia')->firstOrFail()->outcomes);
    }
}
