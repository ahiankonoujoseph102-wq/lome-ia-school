<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Afi Dossou',
            'email' => 'afi@example.com',
            'phone_country' => '+228',
            'phone' => '90 12 34 56',
            'password' => 'motdepasse1',
        ], $overrides);
    }

    public function test_registration_page_shows_the_whatsapp_field_with_togo_by_default(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Créez votre compte')
            ->assertSee('Numéro WhatsApp')
            ->assertSee('<option value="+228" selected', false)
            ->assertSee('Afficher');
    }

    public function test_student_registers_with_whatsapp_number_and_receives_a_welcome_email(): void
    {
        Mail::fake();

        $this->post(route('register.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('email-confirmation.show', absolute: false));

        $user = User::where('email', 'afi@example.com')->firstOrFail();
        $this->assertSame('+22890123456', $user->phone);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertAuthenticatedAs($user);

        Mail::assertQueued(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('afi@example.com'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function internationalNumbers(): array
    {
        return [
            'bénin, nouveau numéro à 10 chiffres' => ['+229', '01 94 55 59 14', '+2290194555914'],
            'bénin, numéro complet avec +' => ['+228', '+229 01 94 55 59 14', '+2290194555914'],
            'bénin, ancien numéro à 8 chiffres' => ['+229', '97 00 11 22', '+2290197001122'],
            'burkina faso, 0 initial conservé' => ['+226', '07 27 63 81', '+22607276381'],
            'burkina faso, numéro complet avec 00' => ['+228', '00226 03 53 21 76', '+22603532176'],
            'côte d\'ivoire, 10 chiffres' => ['+225', '07 07 12 34 56', '+2250707123456'],
            'sénégal' => ['+221', '77 123 45 67', '+221771234567'],
            'mali' => ['+223', '76 12 34 56', '+22376123456'],
            'niger' => ['+227', '96 12 34 56', '+22796123456'],
            'numéro complet avec +' => ['+228', '+33 6 12 34 56 78', '+33612345678'],
            'numéro complet avec 00' => ['+228', '00233 24 123 4567', '+233241234567'],
        ];
    }

    #[DataProvider('internationalNumbers')]
    public function test_other_countries_are_accepted(string $country, string $input, string $expected): void
    {
        Mail::fake();

        $this->post(route('register.store'), $this->payload(['phone_country' => $country, 'phone' => $input]))
            ->assertSessionHasNoErrors();

        $this->assertSame($expected, User::firstOrFail()->phone);
    }

    public function test_whatsapp_number_is_required(): void
    {
        $this->post(route('register.store'), $this->payload(['phone' => '']))
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_invalid_whatsapp_number_is_rejected(): void
    {
        $this->post(route('register.store'), $this->payload(['phone' => '12 34']))
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_whatsapp_number_must_be_unique(): void
    {
        User::factory()->create(['phone' => '+22890123456']);

        $this->post(route('register.store'), $this->payload(['phone' => '+228 90 12 34 56']))
            ->assertSessionHasErrors(['phone' => 'Ce numéro WhatsApp est déjà utilisé par un autre compte.']);
    }

    public function test_uniqueness_works_whatever_the_way_the_number_is_typed(): void
    {
        User::factory()->create(['phone' => '+2290194555914']);

        $this->post(route('register.store'), $this->payload(['phone_country' => '+229', 'phone' => '01 94 55 59 14']))
            ->assertSessionHasErrors(['phone' => 'Ce numéro WhatsApp est déjà utilisé par un autre compte.']);
        $this->post(route('register.store'), $this->payload(['phone_country' => '+228', 'phone' => '+229 0194555914']))
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_wrong_length_for_the_country_is_rejected(): void
    {
        $this->post(route('register.store'), $this->payload(['phone_country' => '+226', 'phone' => '07 27 63']))
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_welcome_email_is_in_french(): void
    {
        $user = User::factory()->create(['name' => 'Yawa Amegah']);

        $mail = new WelcomeMail($user);

        $mail->assertHasSubject('Bienvenue chez Lomé IA School, Yawa !');
        $mail->assertSeeInHtml('Bienvenue Yawa !');
        $mail->assertSeeInHtml('Découvrir les formations');
        $mail->assertSeeInText('Payez par Mobile Money');
    }

    public function test_login_page_uses_the_site_design(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Bon retour parmi nous')
            ->assertSee('Mot de passe oublié ?')
            ->assertSee('Créer un compte gratuitement')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
