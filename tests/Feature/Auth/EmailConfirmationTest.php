<?php

namespace Tests\Feature\Auth;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Mail\EmailConfirmationMail;
use App\Models\AdminActivity;
use App\Models\Affiliate;
use App\Models\EmailVerification;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function register(string $email = 'afi@example.com'): User
    {
        $this->post(route('register.store'), [
            'name' => 'Afi Mensah',
            'email' => $email,
            'phone_country' => '+228',
            'phone' => '90 12 34 56',
            'password' => 'motdepasse1',
        ])->assertRedirect(route('email-confirmation.show'));

        return User::query()->where('email', $email)->firstOrFail();
    }

    /**
     * Dernier e-mail de confirmation envoyé à cette adresse.
     */
    private function lastMail(string $email = 'afi@example.com'): EmailConfirmationMail
    {
        $mail = Mail::sent(EmailConfirmationMail::class, fn (EmailConfirmationMail $mail): bool => $mail->hasTo($email))->last();
        $this->assertInstanceOf(EmailConfirmationMail::class, $mail);

        return $mail;
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    public function test_registration_sends_a_six_digit_code_and_a_confirmation_button(): void
    {
        $user = $this->register();

        $this->assertFalse($user->hasConfirmedEmail());

        $mail = $this->lastMail();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->code);
        $this->assertSame(64, strlen($mail->token));
        // Jamais par la file d'attente : le code n'est jamais enregistré en clair dans la table « jobs ».
        Mail::assertNotQueued(EmailConfirmationMail::class);

        $html = $mail->render();
        $this->assertStringContainsString($mail->code, $html);
        $this->assertStringContainsString(route('email-confirmation.link', $mail->token), $html);
        $this->assertStringContainsString('valables 30 minutes', $html);

        // Code et lien jamais en clair dans la base.
        $verification = EmailVerification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame($mail->code, $verification->code_hash);
        $this->assertSame(hash('sha256', $mail->token), $verification->token_hash);
    }

    public function test_confirmation_page_shows_the_address_the_spam_hint_and_the_resend_and_change_options(): void
    {
        $this->register();

        $this->get(route('email-confirmation.show'))
            ->assertOk()
            ->assertSee('Confirmez votre e-mail')
            ->assertSee('afi@example.com')
            ->assertSee('Vérifiez aussi vos courriers indésirables (spam).')
            ->assertSee('Renvoyer le code')
            ->assertSee('Adresse mal tapée ?');
    }

    public function test_the_code_confirms_the_address_only_once(): void
    {
        $user = $this->register();
        $code = $this->lastMail()->code;

        $this->post(route('email-confirmation.confirm'), ['code' => substr($code, 0, 3).' '.substr($code, 3)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($user->refresh()->hasConfirmedEmail());
        $this->assertDatabaseMissing('email_verifications', ['user_id' => $user->id]);

        // Usage unique : le code et le lien ne fonctionnent plus.
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user);
        $this->post(route('email-confirmation.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->refresh()->hasConfirmedEmail());
    }

    public function test_code_expires_after_30_minutes(): void
    {
        $user = $this->register();
        $code = $this->lastMail()->code;

        $this->travel(31)->minutes();

        $this->post(route('email-confirmation.confirm'), ['code' => $code])->assertSessionHasErrors(['code' => 'Ce code n\'est plus valable (il expire au bout de 30 minutes). Demandez un nouveau code.']);
        $this->assertFalse($user->refresh()->hasConfirmedEmail());
    }

    public function test_too_many_wrong_codes_block_the_entry_then_the_code_no_longer_works(): void
    {
        $user = $this->register();
        $code = $this->lastMail()->code;
        $wrong = $this->wrongCode($code);

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('email-confirmation.confirm'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->assertStringContainsString('1 essai restant', session('errors')->first('code'));

        $this->post(route('email-confirmation.confirm'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertStringContainsString('bloquée pendant 15 minutes', session('errors')->first('code'));

        // Même le bon code est refusé pendant le blocage.
        $this->post(route('email-confirmation.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertStringContainsString('bloquée', session('errors')->first('code'));
        $this->get(route('email-confirmation.show'))->assertSee('data-test="confirmation-locked"', false);

        // Après le blocage : le code a été annulé, il faut en demander un nouveau.
        $this->travel(16)->minutes();
        $this->post(route('email-confirmation.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertStringContainsString('n\'est plus valable', session('errors')->first('code'));
        $this->assertFalse($user->refresh()->hasConfirmedEmail());

        $this->post(route('email-confirmation.resend'))->assertSessionHasNoErrors();
        $this->post(route('email-confirmation.confirm'), ['code' => $this->lastMail()->code])->assertSessionHasNoErrors();
        $this->assertTrue($user->refresh()->hasConfirmedEmail());
    }

    public function test_email_button_confirms_the_address_even_when_logged_out_and_only_once(): void
    {
        $user = $this->register();
        $token = $this->lastMail()->token;
        auth()->logout();

        $this->get(route('email-confirmation.link', $token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Merci, votre adresse e-mail est confirmée. Connectez-vous pour continuer.');
        $this->assertTrue($user->refresh()->hasConfirmedEmail());

        $this->get(route('email-confirmation.link', $token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');
    }

    public function test_email_button_expires_after_30_minutes(): void
    {
        $user = $this->register();
        $token = $this->lastMail()->token;

        $this->travel(31)->minutes();

        $this->get(route('email-confirmation.link', $token))->assertRedirect(route('email-confirmation.show'))->assertSessionHasErrors('code');
        $this->assertFalse($user->refresh()->hasConfirmedEmail());
    }

    public function test_email_button_brings_the_student_back_to_the_order_summary(): void
    {
        $formation = Formation::factory()->create();
        $this->get(route('checkout.show', $formation))->assertOk();
        $user = $this->register();

        $this->get(route('email-confirmation.link', $this->lastMail()->token))->assertRedirect(route('checkout.show', $formation));
        $this->assertTrue($user->refresh()->hasConfirmedEmail());
    }

    public function test_resending_is_limited_to_once_a_minute_and_five_times_an_hour_and_replaces_the_old_code(): void
    {
        $user = $this->register();
        $first = $this->lastMail();

        // Moins d'une minute après l'inscription : refusé.
        $this->post(route('email-confirmation.resend'))->assertSessionHasErrors('resend');
        Mail::assertSent(EmailConfirmationMail::class, 1);

        $this->travel(61)->seconds();
        $this->post(route('email-confirmation.resend'))->assertSessionHasNoErrors()->assertRedirect(route('email-confirmation.show'));
        $second = $this->lastMail();

        // L'ancien code et l'ancien lien ne fonctionnent plus.
        if ($first->code !== $second->code) {
            $this->post(route('email-confirmation.confirm'), ['code' => $first->code])->assertSessionHasErrors('code');
        }
        $this->get(route('email-confirmation.link', $first->token))->assertSessionHasErrors('code');
        $this->assertFalse($user->refresh()->hasConfirmedEmail());

        // 5 envois par heure au maximum (inscription comprise).
        for ($i = 0; $i < 3; $i++) {
            $this->travel(61)->seconds();
            $this->post(route('email-confirmation.resend'))->assertSessionHasNoErrors();
        }
        Mail::assertSent(EmailConfirmationMail::class, 5);

        $this->travel(61)->seconds();
        $this->post(route('email-confirmation.resend'))->assertSessionHasErrors('resend');
        Mail::assertSent(EmailConfirmationMail::class, 5);

        $this->travel(1)->hours();
        $this->post(route('email-confirmation.resend'))->assertSessionHasNoErrors();
        Mail::assertSent(EmailConfirmationMail::class, 6);
    }

    public function test_a_mistyped_address_can_be_corrected_and_receives_a_new_code(): void
    {
        $user = $this->register('afi@exmple.com');
        User::factory()->create(['email' => 'pris@example.com']);
        $this->travel(61)->seconds();

        $this->put(route('email-confirmation.email'), ['email' => 'pris@example.com'])->assertSessionHasErrorsIn('changeEmail', 'email');

        $this->put(route('email-confirmation.email'), ['email' => ' Afi@Example.com '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('email-confirmation.show'));

        $this->assertSame('afi@example.com', $user->refresh()->email);
        $mail = $this->lastMail('afi@example.com');

        // Le code envoyé à l'ancienne adresse ne fonctionne plus.
        $old = $this->lastMail('afi@exmple.com');
        $this->get(route('email-confirmation.link', $old->token))->assertSessionHasErrors('code');

        $this->post(route('email-confirmation.confirm'), ['code' => $mail->code])->assertSessionHasNoErrors();
        $this->assertTrue($user->refresh()->hasConfirmedEmail());
    }

    public function test_changing_the_address_follows_the_sending_limits(): void
    {
        $this->register('afi@exmple.com');

        $this->put(route('email-confirmation.email'), ['email' => 'afi@example.com'])->assertSessionHasErrorsIn('changeEmail', 'email');
        Mail::assertSent(EmailConfirmationMail::class, 1);
    }

    public function test_unconfirmed_student_can_visit_but_not_pay_a_formation(): void
    {
        $student = User::factory()->unverified()->create();
        $formation = Formation::factory()->create();

        $this->actingAs($student)->get(route('formations.show', $formation))->assertOk();
        $this->actingAs($student)->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('data-test="email-confirmation-required"', false)
            ->assertDontSee('data-test="send-proof-button"', false);

        $this->actingAs($student)
            ->from(route('checkout.show', $formation))
            ->post(route('checkout.pay', $formation), [
                'payment_method' => 'tg-mixx',
                'payer_phone' => '90 12 34 56',
                'payer_name' => 'Afi Dossou',
                'transaction_id' => 'MP261010.1001.A12345',
                'proof' => UploadedFile::fake()->image('capture.png', 600, 1200),
            ])
            ->assertRedirect(route('email-confirmation.show'));

        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(route('checkout.show', $formation), session('url.intended'));
    }

    public function test_unconfirmed_student_cannot_pay_a_session_supplement_or_request_a_withdrawal(): void
    {
        $student = User::factory()->unverified()->create();
        $session = LiveSession::factory()->create();

        $this->actingAs($student)->post(route('student.sessions.pay', $session))->assertRedirect(route('email-confirmation.show'));

        Affiliate::query()->create(['user_id' => $student->id, 'code' => 'AFI123', 'terms_accepted_at' => now()]);
        $this->actingAs($student)->get(route('student.affiliate.withdrawals'))
            ->assertOk()
            ->assertSee('Confirmez d&#039;abord votre adresse e-mail pour demander un retrait.', false);
        $this->actingAs($student)->post(route('student.affiliate.withdraw'))->assertRedirect(route('email-confirmation.show'));
        $this->assertDatabaseCount('affiliate_withdrawals', 0);
    }

    public function test_banner_invites_unconfirmed_students_to_confirm_their_address(): void
    {
        $unconfirmed = User::factory()->unverified()->create();
        $confirmed = User::factory()->create();

        $this->actingAs($unconfirmed)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="email-banner"', false)
            ->assertSee(route('email-confirmation.show'));
        $this->actingAs($unconfirmed)->get(route('email-confirmation.show'))->assertDontSee('data-test="email-banner"', false);
        $this->actingAs($confirmed)->get(route('dashboard'))->assertDontSee('data-test="email-banner"', false);
        $this->actingAs($confirmed)->get(route('email-confirmation.show'))->assertRedirect(route('dashboard'));
    }

    public function test_new_address_in_the_profile_must_be_confirmed_the_same_way(): void
    {
        $student = User::factory()->create(['email' => 'afi@example.com']);

        $this->actingAs($student)->put(route('student.profile.update'), [
            'name' => $student->name,
            'email' => 'afi.nouvelle@example.com',
            'phone_country' => '+228',
            'phone' => '90 12 34 56',
        ])->assertSessionHasNoErrors()->assertRedirect(route('email-confirmation.show'));

        $this->assertFalse($student->refresh()->hasConfirmedEmail());
        $mail = $this->lastMail('afi.nouvelle@example.com');

        $this->actingAs($student)->post(route('email-confirmation.confirm'), ['code' => $mail->code])->assertSessionHasNoErrors();
        $this->assertTrue($student->refresh()->hasConfirmedEmail());
    }

    public function test_admin_sees_the_status_and_can_confirm_manually_with_a_log_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->unverified()->create();

        $this->actingAs($admin)->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('E-mail non confirmé')
            ->assertSee('Confirmer l\'email manuellement', false);

        $this->actingAs($admin)->post(route('admin.students.confirm-email', $student))->assertRedirect();

        $this->assertTrue($student->refresh()->hasConfirmedEmail());
        $activity = AdminActivity::query()->where('action', AdminAction::EmailConfirmed)->firstOrFail();
        $this->assertSame($admin->id, $activity->user_id);
        $this->assertSame($student->id, $activity->subject_id);

        $this->actingAs($admin)->get(route('admin.students.show', $student))
            ->assertSee('E-mail confirmé le')
            ->assertDontSee('data-test="confirm-email"', false);
        $this->actingAs($admin)->get(route('admin.activity.index'))->assertSee('E-mail confirmé manuellement');
        $this->actingAs($admin)->get(route('admin.students.index', ['filtre' => 'email-non-confirme']))->assertDontSee($student->name);
    }

    public function test_only_the_admin_can_confirm_an_address_manually(): void
    {
        $student = User::factory()->unverified()->create();
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->post(route('admin.students.confirm-email', $student))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.students.confirm-email', $student))->assertForbidden();
        $this->assertFalse($student->refresh()->hasConfirmedEmail());
    }

    public function test_privacy_policy_explains_the_email_confirmation(): void
    {
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Confirmation de l\'adresse e-mail', false);
    }
}
