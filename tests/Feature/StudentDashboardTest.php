<?php

namespace Tests\Feature;

use App\Enums\EnrollmentSource;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_without_formation_sees_an_inviting_empty_state(): void
    {
        $user = User::factory()->create(['name' => 'Kafui Adjo']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bonjour Kafui !')
            ->assertSee('Votre première formation vous attend')
            ->assertSee('Découvrir le catalogue')
            ->assertSee('Sessions en direct ou à Lomé')
            ->assertSee('Mes groupes de discussion')
            ->assertSee('Affiliation')
            ->assertSee('Recommandez nos formations et recevez 10 % de chaque achat validé passé par votre lien.')
            ->assertDontSee('Bientôt disponible');
    }

    public function test_student_sees_their_formations(): void
    {
        $user = User::factory()->create();
        $first = Formation::factory()->create(['title' => 'Formation ChatGPT & Claude']);
        $second = Formation::factory()->create(['title' => 'Automatisation avec l\'IA']);
        $user->enroll($first, EnrollmentSource::Purchase);
        $user->enroll($second, EnrollmentSource::Admin);
        Formation::factory()->create(['title' => 'Formation non achetée']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Formation ChatGPT &amp; Claude', false)
            ->assertSee('Automatisation avec l&#039;IA', false)
            ->assertSee(route('student.formations.show', $first), false)
            ->assertDontSee('Votre première formation vous attend');

        $this->actingAs($user)->get(route('student.formations.index'))
            ->assertOk()
            ->assertSee('2 formations dans votre espace')
            ->assertDontSee('Formation non achetée');
    }

    public function test_student_space_has_mobile_navigation(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertSee('aria-label="Mon espace"', false)
            ->assertSee(route('student.formations.index'), false)
            ->assertSee(route('student.profile'), false);
    }

    public function test_site_header_shows_the_student_initials_and_quick_access(): void
    {
        $user = User::factory()->create(['name' => 'Kossi Mensah']);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('KM')
            ->assertSee('aria-label="Mon espace"', false)
            ->assertSee('Se déconnecter');
    }

    public function test_old_dashboard_url_redirects_to_the_student_space(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/mon-espace');
    }
}
