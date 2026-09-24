<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_the_hero_and_featured_formations(): void
    {
        $featured = Formation::factory()->featured()->create(['title' => 'Formation mise en avant']);
        Formation::factory()->create(['title' => 'Formation non mise en avant']);
        Formation::factory()->featured()->inactive()->create(['title' => 'Formation désactivée']);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Maîtrisez l&#039;IA et transformez vos compétences en opportunités', false)
            ->assertSee('Voir les formations')
            ->assertSee('Réserver une consultation gratuite')
            ->assertSee($featured->title)
            ->assertSee(route('formations.show', $featured))
            ->assertDontSee('Formation non mise en avant')
            ->assertDontSee('Formation désactivée')
            ->assertSee('Gagnez 10 % en recommandant nos formations');
    }

    public function test_home_page_has_seo_and_sharing_tags(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:image" content="'.route('og.site').'"', false)
            ->assertSee('"@type":"EducationalOrganization"', false);
    }

    public function test_header_shows_login_for_guests_and_student_space_for_members(): void
    {
        $this->get(route('home'))->assertSee('Connexion')->assertDontSee('Mon espace');

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertSee('Mon espace');
    }

    public function test_whatsapp_button_opens_a_prefilled_conversation(): void
    {
        $this->get(route('home'))
            ->assertSee('https://wa.me/22872240231?text=', false);
    }
}
