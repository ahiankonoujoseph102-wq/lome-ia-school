<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Codes de récupération de la double authentification : très visibles à l'activation, copiables,
 * renouvelables, et utilisables pour se connecter si le téléphone est perdu.
 */
class RecoveryCodeTest extends TestCase
{
    use RefreshDatabase;

    private const WARNING = 'Notez ces codes hors de votre téléphone. Sans eux, la perte du téléphone vous empêche d&#039;accéder à l&#039;administration.';

    private function admin(): User
    {
        return User::factory()->withTwoFactor()->create(['role' => UserRole::Admin]);
    }

    public function test_codes_are_shown_prominently_right_after_activation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time(), 'status' => 'two-factor-authentication-confirmed'])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee(self::WARNING, false)
            ->assertSee('recovery-code-1')
            ->assertSee('Copier les codes')
            ->assertSee('Générer de nouveaux codes')
            ->assertSee('<div class="mt-5 rounded-2xl p-4 sm:p-5 border-2 border-gold-400 bg-gold-400/10"', false);

        // Un élève : même avertissement, adapté à son compte.
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->withSession(['auth.password_confirmed_at' => time(), 'status' => 'two-factor-authentication-confirmed'])
            ->get(route('security.edit'))
            ->assertSee('la perte du téléphone vous empêche d&#039;accéder à votre compte.', false);
    }

    public function test_new_codes_can_be_generated(): void
    {
        $admin = $this->admin();
        $before = $admin->recoveryCodes();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.regenerate-recovery-codes'))
            ->assertRedirect();

        $after = $admin->fresh()?->recoveryCodes() ?? [];
        $this->assertCount(8, $after);
        $this->assertNotSame($before, $after);

        $this->actingAs($admin->fresh() ?? $admin)
            ->withSession(['auth.password_confirmed_at' => time(), 'status' => 'recovery-codes-generated'])
            ->get(route('security.edit'))
            ->assertSee($after[0])
            ->assertSee(self::WARNING, false);
    }

    public function test_login_with_a_recovery_code_works_once(): void
    {
        $admin = $this->admin();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));

        // Mauvais code : refusé.
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'mauvais-code'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect();
        $this->assertAuthenticatedAs($admin);

        // Connecté avec la double authentification : l'administration s'ouvre.
        $this->get(route('admin.index'))->assertOk();

        // Le code utilisé est remplacé : il ne fonctionne plus.
        $this->assertNotContains('recovery-code-1', $admin->fresh()?->recoveryCodes() ?? []);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }
}
