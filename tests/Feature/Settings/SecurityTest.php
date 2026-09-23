<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function actingWithConfirmedPassword(User $user): static
    {
        // Recharge le compte pour refléter les changements faits par la requête précédente.
        return $this->actingAs($user->fresh() ?? $user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_security_page_uses_the_site_design(): void
    {
        $this->actingWithConfirmedPassword(User::factory()->create())
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Sécurité du compte')
            ->assertSee('Modifier le mot de passe')
            ->assertSee('Double authentification')
            ->assertSee('Activer la double authentification')
            ->assertSee('Passkeys')
            ->assertSee('Aucune passkey pour le moment')
            ->assertSee('aria-label="Mon espace"', false);
    }

    public function test_security_page_requires_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_password_confirmation_page_uses_the_site_design(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('password.confirm'))
            ->assertOk()
            ->assertSee('Zone sécurisée');
    }

    public function test_old_settings_urls_redirect_to_the_new_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/settings/security')->assertRedirect('/mon-espace/securite');
        $this->get('/settings/appearance')->assertRedirect('/mon-espace/profil');
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        config(['fortify.features' => [Features::passkeys(['confirmPassword' => true])]]);

        $this->actingWithConfirmedPassword(User::factory()->create())
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Modifier le mot de passe')
            ->assertDontSee('Double authentification');
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingWithConfirmedPassword($user)
            ->put(route('security.password.update'), [
                'current_password' => 'password',
                'password' => 'nouveau-mdp-2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('nouveau-mdp-2026', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingWithConfirmedPassword($user)
            ->put(route('security.password.update'), [
                'current_password' => 'mauvais',
                'password' => 'nouveau-mdp-2026',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_enabling_two_factor_shows_the_qr_code_and_setup_key(): void
    {
        $user = User::factory()->create();

        $this->actingWithConfirmedPassword($user)
            ->from(route('security.edit'))
            ->post(route('two-factor.enable'))
            ->assertRedirect(route('security.edit'));

        $this->actingWithConfirmedPassword($user)
            ->withSession(['status' => 'two-factor-authentication-enabled'])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Scannez ce QR code')
            ->assertSee('<svg', false)
            ->assertSee('Saisissez le code à 6 chiffres');
    }

    public function test_wrong_confirmation_code_keeps_the_setup_open(): void
    {
        $user = User::factory()->create();
        $this->actingWithConfirmedPassword($user)->from(route('security.edit'))->post(route('two-factor.enable'));

        $this->actingWithConfirmedPassword($user)
            ->from(route('security.edit'))
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertRedirect(route('security.edit'));

        $this->get(route('security.edit'))
            ->assertSee('Code incorrect');

        $this->assertNotNull($user->refresh()->two_factor_secret);
    }

    public function test_unfinished_two_factor_setup_can_be_resumed_or_cancelled(): void
    {
        $user = User::factory()->create();
        $this->actingWithConfirmedPassword($user)->from(route('security.edit'))->post(route('two-factor.enable'));

        $this->actingWithConfirmedPassword($user)
            ->get(route('security.edit'))
            ->assertSee('Scannez ce QR code')
            ->assertSee("Annuler l'activation", false);

        $this->actingWithConfirmedPassword($user)
            ->from(route('security.edit'))
            ->delete(route('two-factor.disable'))
            ->assertRedirect(route('security.edit'));

        $this->assertNull($user->refresh()->two_factor_secret);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
    }

    public function test_enabled_two_factor_shows_recovery_codes_and_disable_button(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingWithConfirmedPassword($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Activée')
            ->assertSee('recovery-code-1')
            ->assertSee('Désactiver la double authentification');
    }

    public function test_two_factor_challenge_uses_the_site_design(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->withSession(['login.id' => $user->id, 'login.remember' => false])
            ->get(route('two-factor.login'))
            ->assertOk()
            ->assertSee('Vérification en deux étapes')
            ->assertSee('Utiliser un code de récupération');
    }
}
