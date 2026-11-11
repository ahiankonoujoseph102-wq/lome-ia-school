<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use App\Support\FormationOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page d'une formation (novembre 2026) : « L'essentiel », « Comment se déroule la formation » et la barre
 * « Sur cette page ». Uniquement des faits vrais : jamais de durée ni de niveau inventés.
 */
class FormationPageOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_essentials_journey_and_section_nav_are_shown(): void
    {
        $formation = Formation::factory()->create(['price' => 10000, 'outcomes' => ['Créer un agent']]);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('data-test="formation-essentials"', false)
            ->assertSee("L'essentiel", false)
            ->assertSee('Vidéos pas à pas')
            ->assertSee('Avec les documents de chaque leçon')
            ->assertSee('Téléphone ou ordinateur')
            ->assertSee('Accès immédiat')
            ->assertSee('Accompagnement de 3 mois')
            ->assertSee('data-test="formation-journey"', false)
            ->assertSee('Comment se déroule la formation')
            ->assertSee('Vous payez par Mobile Money')
            ->assertSee('Depuis le Togo, le Burkina Faso, le Bénin ou la Côte d&#039;Ivoire', false)
            ->assertSee('Vous recevez votre attestation')
            ->assertSee('data-test="formation-section-nav"', false)
            ->assertSee('href="#apprendre"', false)
            ->assertSee('id="apprendre"', false)
            ->assertDontSee('href="#programme"', false);
    }

    public function test_a_formation_above_the_threshold_says_access_after_verification(): void
    {
        $formation = Formation::factory()->create(['price' => 150000]);

        $essentials = collect(FormationOverview::essentials($formation))->pluck('title');
        $this->assertContains('Accès après vérification', $essentials);
        $this->assertNotContains('Accès immédiat', $essentials);

        $this->get(route('formations.show', $formation))->assertOk()->assertSee('Dès que votre paiement est vérifié');
    }

    public function test_nothing_unknown_is_invented(): void
    {
        $formation = Formation::factory()->create(['price' => 10000]);
        $text = json_encode([FormationOverview::essentials($formation), FormationOverview::journey($formation)], JSON_UNESCAPED_UNICODE);

        foreach (['heure', 'semaine', 'débutant', 'niveau', 'garanti', '100 %'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, (string) $text, $word);
        }
    }
}
