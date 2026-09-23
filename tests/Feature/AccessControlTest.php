<?php

namespace Tests\Feature;

use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_are_students(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Student, $user->refresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_role_cannot_be_set_through_mass_assignment(): void
    {
        $user = new User(['name' => 'Test', 'email' => 't@example.com', 'password' => 'x', 'role' => 'admin']);

        $this->assertSame(UserRole::Student, $user->role);
    }

    public function test_admin_area_is_reserved_to_admins(): void
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.index'))->assertOk()->assertSee('Administration');
    }

    public function test_only_enrolled_students_and_admins_can_access_a_formation(): void
    {
        $formation = Formation::factory()->create();
        $enrolled = User::factory()->create();
        $enrolled->enroll($formation, EnrollmentSource::Purchase);
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($enrolled)->allows('access', $formation));
        $this->assertFalse(Gate::forUser($other)->allows('access', $formation));
        $this->assertTrue(Gate::forUser($admin)->allows('access', $formation));
    }

    public function test_formation_space_is_protected_by_the_access_rule(): void
    {
        $formation = Formation::factory()->create(['title' => 'Création d\'agents IA']);
        $student = User::factory()->create();

        $this->get(route('student.formations.show', $formation))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('student.formations.show', $formation))->assertForbidden();

        $student->enroll($formation, EnrollmentSource::Admin);

        $this->actingAs($student)->get(route('student.formations.show', $formation))
            ->assertOk()
            ->assertSee('Création d&#039;agents IA', false)
            ->assertSee('Lecteur de cours');
    }

    public function test_enrolling_twice_does_not_create_duplicates(): void
    {
        $formation = Formation::factory()->create();
        $student = User::factory()->create();

        $student->enroll($formation, EnrollmentSource::Purchase);
        $student->enroll($formation, EnrollmentSource::Admin);

        $this->assertSame(1, $student->enrollments()->count());
        $this->assertSame(EnrollmentSource::Purchase, $student->enrollments()->first()?->source);
    }
}
