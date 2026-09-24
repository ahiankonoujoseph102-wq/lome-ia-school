<?php

namespace Tests\Feature\Site;

use App\Enums\FormationCategory;
use App\Models\Formation;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_lists_active_formations_with_filters(): void
    {
        $active = Formation::factory()->create(['title' => 'Formation active', 'category' => FormationCategory::Web]);
        Formation::factory()->inactive()->create(['title' => 'Formation masquée']);

        $this->get(route('formations.index'))
            ->assertOk()
            ->assertSee($active->title)
            ->assertSee(Money::fcfa($active->price))
            ->assertDontSee('Formation masquée')
            ->assertSee('Web, applications et SaaS')
            ->assertSee('Rechercher une formation');
    }

    public function test_formation_page_shows_sales_content_and_buy_button(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Création d\'agents IA',
            'slug' => 'creation-agents-ia',
            'price' => 30000,
            'outcomes' => ['Concevoir un agent adapté à un besoin précis'],
            'curriculum' => [['title' => 'Comprendre les agents IA', 'lessons' => ['Les outils pour créer des agents']]],
            'audience' => ['Les freelances'],
        ]);
        config(['site.bonuses.creation-agents-ia' => ['Des modèles d\'instructions']]);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('Création d&#039;agents IA', false)
            ->assertSee("30\u{00A0}000\u{00A0}FCFA")
            ->assertSee('Ce que vous allez apprendre')
            ->assertSee('Concevoir un agent adapté à un besoin précis')
            ->assertSee('Comprendre les agents IA')
            ->assertSee('Les outils pour créer des agents')
            ->assertSee('Pour qui est cette formation ?')
            ->assertSee('Des modèles d&#039;instructions', false)
            ->assertSee('Accès immédiat')
            ->assertSee('Accès à vie')
            ->assertSee('Accompagnement à vie')
            ->assertSee('Joseph Ahiankonou')
            ->assertSee('Acheter maintenant')
            ->assertSee(route('checkout.show', 'creation-agents-ia'), false);
    }

    public function test_formation_page_has_open_graph_preview_and_course_schema(): void
    {
        $formation = Formation::factory()->create(['slug' => 'automatisation-ia']);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="'.e($formation->title).'"', false)
            ->assertSee('<meta property="og:image" content="'.route('og.formation', $formation), false)
            ->assertSee('<meta property="og:url" content="'.route('formations.show', $formation).'"', false)
            ->assertSee('"@type":"Course"', false)
            ->assertSee('"priceCurrency":"XOF"', false);
    }

    public function test_inactive_or_unknown_formations_are_not_found(): void
    {
        $inactive = Formation::factory()->inactive()->create();

        $this->get(route('formations.show', $inactive))->assertNotFound();
        $this->get('/formations/formation-inexistante')->assertNotFound();
    }
}
