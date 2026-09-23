<?php

namespace Tests\Feature;

use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_admin_promotes_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'joseph@example.com']);

        $this->artisan('app:make-admin', ['email' => 'joseph@example.com'])
            ->expectsOutputToContain('est maintenant administrateur')
            ->assertSuccessful();

        $this->assertSame(UserRole::Admin, $user->refresh()->role);
    }

    public function test_make_admin_creates_an_account_with_a_typed_password(): void
    {
        $this->artisan('app:make-admin', ['email' => 'admin@example.com', '--name' => 'Joseph Ahiankonou', '--phone' => '+228 72 24 02 31'])
            ->expectsQuestion('Mot de passe (8 caractères minimum)', 'un-mot-de-passe-solide')
            ->expectsOutputToContain('Compte administrateur créé')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('+22872240231', $admin->phone);
        $this->assertTrue(Hash::check('un-mot-de-passe-solide', $admin->password));
    }

    public function test_make_admin_generates_a_temporary_password_when_not_interactive(): void
    {
        $this->artisan('app:make-admin', ['email' => 'admin@example.com', '--name' => 'Admin', '--no-interaction' => true])
            ->expectsOutputToContain('Mot de passe provisoire')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'admin@example.com')->firstOrFail()->isAdmin());
    }

    public function test_make_admin_rejects_an_invalid_email(): void
    {
        $this->artisan('app:make-admin', ['email' => 'pas-un-email'])->assertFailed();
    }

    public function test_grant_access_gives_and_revokes_a_formation(): void
    {
        $user = User::factory()->create(['email' => 'eleve@example.com']);
        $formation = Formation::factory()->create(['slug' => 'automatisation-ia']);

        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => 'automatisation-ia'])
            ->expectsOutputToContain('donné')
            ->assertSuccessful();

        $this->assertTrue($user->isEnrolledIn($formation));
        $this->assertSame(EnrollmentSource::Admin, $user->enrollments()->first()?->source);

        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => 'automatisation-ia'])
            ->expectsOutputToContain('déjà accès')
            ->assertSuccessful();

        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => 'automatisation-ia', '--revoke' => true])
            ->expectsOutputToContain('retiré')
            ->assertSuccessful();

        $this->assertFalse($user->isEnrolledIn($formation));
    }

    public function test_grant_access_fails_for_unknown_student_or_formation(): void
    {
        User::factory()->create(['email' => 'eleve@example.com']);

        $this->artisan('app:grant-access', ['email' => 'inconnu@example.com', 'formation' => 'x'])->assertFailed();
        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => 'inexistante'])->assertFailed();
    }
}
