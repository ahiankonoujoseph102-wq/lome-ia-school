<?php

namespace Tests\Feature\FreeRegistrations;

use App\Enums\EnrollmentSource;
use App\FreeRegistrations\PendingFreeChoice;
use App\Http\Controllers\Site\FreeRegistrationController;
use App\Mail\EmailConfirmationMail;
use App\Mail\FreeRegistrationMail;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\FreeRegistrationOption;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Formation gratuite, parcours « compte d'abord » (octobre 2026) : le visiteur crée d'abord son compte
 * (« Créer mon compte »), confirme son e-mail, puis arrive directement sur « Dernière étape : choisissez votre
 * option » et touche « Je confirme mon inscription ». Les options de la page ne sont que des informations.
 */
class FreeRegistrationAccountFirstTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-04 10:00', 'Africa/Lome'));
    }

    private function freeFormation(int $onsiteCapacity = 20, bool $withOptions = true): Formation
    {
        $formation = Formation::factory()->create([
            'title' => 'Formation gratuite exemple',
            'price' => 0,
            'event_date' => '2026-10-10',
            'event_location' => 'Adidogomé, Lycée moderne 2, Lomé',
        ]);

        if ($withOptions) {
            $formation->freeOptions()->create(['label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => $onsiteCapacity, 'position' => 1]);
            $formation->freeOptions()->create(['label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50, 'position' => 2]);
        }

        return $formation;
    }

    private function option(Formation $formation, string $label): FreeRegistrationOption
    {
        return $formation->freeOptions()->where('label', $label)->firstOrFail();
    }

    private function page(Formation $formation): string
    {
        return route('formations.show', $formation).'#inscription';
    }

    private function registerAccount(string $email = 'afi@example.com'): User
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

    private function lastMail(string $email = 'afi@example.com'): EmailConfirmationMail
    {
        $mail = Mail::sent(EmailConfirmationMail::class, fn (EmailConfirmationMail $mail): bool => $mail->hasTo($email))->last();
        $this->assertInstanceOf(EmailConfirmationMail::class, $mail);

        return $mail;
    }

    private function isInGroup(User $user, Formation $formation): bool
    {
        return ChatGroup::forFormation($formation)->members()->where('user_id', $user->id)->whereNull('removed_at')->exists();
    }

    private function fill(FreeRegistrationOption $option, int $count): void
    {
        User::factory()->count($count)->create()->each(fn (User $user) => FreeRegistration::query()->create([
            'formation_id' => $option->formation_id,
            'user_id' => $user->id,
            'free_registration_option_id' => $option->id,
        ]));
    }

    public function test_a_guest_sees_the_create_account_block_and_the_options_as_plain_information(): void
    {
        $formation = $this->freeFormation();

        $html = $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('Pour vous inscrire, créez d\'abord votre compte', false)
            ->assertSee('C\'est gratuit et cela prend 1 minute.', false)
            ->assertSeeInOrder(['data-test="free-steps"', 'Créez votre compte', 'Confirmez votre e-mail', 'Choisissez votre option : vous êtes inscrit'], false)
            ->assertSeeInOrder(['data-test="free-create-account"', 'Créer mon compte', 'data-test="free-login"', 'J&#039;ai déjà un compte', 'data-test="free-options-info"'], false)
            ->assertSee('href="'.route('free-registration.start', $formation).'"', false)
            ->assertSee('href="'.route('free-registration.start', ['formation' => $formation, 'compte' => 'existant']).'"', false)
            ->assertSee('Présentiel journée')
            ->assertSee('18 h à 20 h')
            ->assertSee('20 places restantes')
            ->getContent();

        // Options en simple information : rien à cocher, rien à toucher.
        $inscription = substr($html, (int) strpos($html, 'id="inscription"'));
        $inscription = substr($inscription, 0, (int) strpos($inscription, '</section>'));
        $this->assertStringNotContainsString('type="radio"', $inscription);
        $this->assertStringNotContainsString('<label', $inscription);
        $this->assertStringNotContainsString('<form', $inscription);
        $this->assertStringNotContainsString('data-test="free-option"', $inscription);
        $this->assertStringNotContainsString('data-test="free-pending-reminder"', $html);
    }

    public function test_the_top_and_bottom_buttons_lead_straight_to_the_account_creation(): void
    {
        $formation = $this->freeFormation();
        $start = route('free-registration.start', $formation);

        $this->get(route('formations.show', $formation))
            ->assertSee('<a href="'.$start.'" class="btn-gold" data-test="free-hero-register">', false)
            ->assertSee('<a href="'.$start.'" class="btn-gold shrink-0 !px-5 !py-3" data-test="free-bar-register">', false);

        $this->get($start)
            ->assertRedirect(route('register'))
            ->assertSessionHas(PendingFreeChoice::SESSION_KEY, ['formation_id' => $formation->id, 'option_id' => null])
            ->assertSessionHas('url.intended', route('free-registration.complete'));

        $this->get(route('register'))
            ->assertSee('data-test="free-choice-reminder"', false)
            ->assertSee('Vous créez votre compte pour vous inscrire à la formation gratuite du 10 octobre.', false)
            ->assertSee('Ensuite : confirmez votre e-mail, puis choisissez votre option.', false)
            ->assertDontSee('Changer d\'option', false);
    }

    public function test_full_journey_account_then_email_then_option_then_registered(): void
    {
        $formation = $this->freeFormation();
        $option = $this->option($formation, 'En ligne soir');

        $this->get(route('free-registration.start', $formation))->assertRedirect(route('register'));
        $student = $this->registerAccount();

        $this->get(route('email-confirmation.show'))
            ->assertSee('Vous vous inscrivez à la formation gratuite du 10 octobre.', false)
            ->assertSee('Saisissez le code. Ensuite, vous choisirez votre option.', false);

        // Code confirmé : arrivée directe sur le choix de l'option, aucune place encore prise.
        $this->post(route('email-confirmation.confirm'), ['code' => $this->lastMail()->code])
            ->assertRedirect(route('free-registration.options', $formation));
        $this->assertSame(0, FreeRegistration::query()->count());
        $this->assertNull(session(PendingFreeChoice::SESSION_KEY));

        $this->get(route('free-registration.options', $formation))
            ->assertOk()
            ->assertSee('Dernière étape : choisissez votre option', false)
            ->assertSeeInOrder(['data-test="free-option"', 'Présentiel journée', 'data-test="free-option"', 'En ligne soir', 'Je confirme mon inscription'], false)
            ->assertSee('value="'.$option->id.'"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->post(route('free-registration.store', $formation), ['option' => $option->id])
            ->assertRedirect($this->page($formation))
            ->assertSessionHas('status', FreeRegistrationController::REGISTERED);

        $registration = FreeRegistration::query()->sole();
        $this->assertSame($student->id, $registration->user_id);
        $this->assertSame($option->id, $registration->free_registration_option_id);
        $this->assertSame(EnrollmentSource::Free, $student->enrollments()->where('formation_id', $formation->id)->sole()->source);
        $this->assertTrue($this->isInGroup($student, $formation));
        Mail::assertQueued(FreeRegistrationMail::class, fn (FreeRegistrationMail $mail): bool => $mail->hasTo('afi@example.com'));

        $this->get(route('formations.show', $formation))
            ->assertSee('data-test="free-registered"', false)
            ->assertSee('Vous êtes inscrit(e)')
            ->assertDontSee('data-test="free-pending-reminder"', false);

        // Déjà inscrit(e) : l'écran du choix renvoie à l'inscription.
        $this->get(route('free-registration.options', $formation))->assertRedirect($this->page($formation));
    }

    public function test_i_already_have_an_account_leads_to_the_option_choice_after_login(): void
    {
        $formation = $this->freeFormation();
        User::factory()->create(['email' => 'kossi@example.com']);

        $this->get(route('free-registration.start', ['formation' => $formation, 'compte' => 'existant']))
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertSee('Vous vous connectez pour vous inscrire à la formation gratuite du 10 octobre.', false)
            ->assertSee('Ensuite, vous choisirez votre option.', false);

        $this->post(route('login.store'), ['email' => 'kossi@example.com', 'password' => 'password'])
            ->assertRedirect(route('free-registration.complete'));

        $this->get(route('free-registration.complete'))
            ->assertRedirect(route('free-registration.options', $formation));
        $this->assertSame(0, FreeRegistration::query()->count());
    }

    public function test_a_login_with_an_unconfirmed_address_goes_through_the_code_then_the_option_choice(): void
    {
        $formation = $this->freeFormation();
        User::factory()->unverified()->create(['email' => 'ama@example.com']);

        $this->get(route('free-registration.start', ['formation' => $formation, 'compte' => 'existant']));
        $this->post(route('login.store'), ['email' => 'ama@example.com', 'password' => 'password']);

        $this->get(route('free-registration.complete'))->assertRedirect(route('email-confirmation.show'));
        $this->post(route('email-confirmation.resend'));

        $this->post(route('email-confirmation.confirm'), ['code' => $this->lastMail('ama@example.com')->code])
            ->assertRedirect(route('free-registration.options', $formation));
    }

    public function test_the_email_button_opened_on_another_device_leads_to_the_option_choice_after_login(): void
    {
        $formation = $this->freeFormation();

        $this->get(route('free-registration.start', $formation));
        $student = $this->registerAccount();
        $this->get(route('email-confirmation.show'));
        $token = $this->lastMail()->token;

        Auth::logout();
        $this->flushSession();

        $this->get(route('email-confirmation.link', $token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('free-registration.options', $formation));

        $this->assertTrue($student->fresh()->hasConfirmedEmail());
        $this->assertSame(0, FreeRegistration::query()->count());
    }

    public function test_a_logged_in_person_without_option_sees_a_reminder_at_the_top(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('formations.show', $formation))
            ->assertSee('data-test="free-pending-reminder"', false)
            ->assertSee('Votre compte est créé, mais vous n\'êtes pas encore inscrit.', false)
            ->assertSee('Choisissez votre option.', false)
            ->assertSeeInOrder(['data-test="free-pending-reminder"', 'href="'.route('free-registration.options', $formation).'"', 'Choisir mon option'], false)
            ->assertSee('Dernière étape : choisissez votre option', false)
            ->assertSee('data-test="free-choose-option"', false)
            // Boutons du haut et de la barre du bas : directement le choix de l'option.
            ->assertSee('<a href="'.route('free-registration.options', $formation).'" class="btn-gold shrink-0 !px-5 !py-3" data-test="free-bar-register">', false);

        // Adresse pas encore confirmée : le rappel mène d'abord à la confirmation.
        $unconfirmed = User::factory()->unverified()->create();
        $this->actingAs($unconfirmed)->get(route('formations.show', $formation))
            ->assertSee('Confirmez votre e-mail, puis choisissez votre option.', false)
            ->assertSee('data-test="free-confirm-email"', false);

        $this->actingAs($unconfirmed)->get(route('free-registration.options', $formation))
            ->assertRedirect(route('email-confirmation.show'));
    }

    public function test_no_reminder_once_registered_closed_or_full(): void
    {
        $formation = $this->freeFormation(onsiteCapacity: 1);
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'Présentiel journée')->id]);
        $this->actingAs($student)->get(route('formations.show', $formation))->assertDontSee('data-test="free-pending-reminder"', false);

        // Option pleine : jamais proposée sur l'écran du choix.
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('free-registration.options', $formation))
            ->assertDontSee('value="'.$this->option($formation, 'Présentiel journée')->id.'"', false)
            ->assertSee('value="'.$this->option($formation, 'En ligne soir')->id.'"', false);

        // Tout est complet : « Complet », sans rappel.
        $this->fill($this->option($formation, 'En ligne soir'), 50);
        $this->actingAs($other)->get(route('formations.show', $formation))
            ->assertSee('data-test="free-full"', false)
            ->assertDontSee('data-test="free-pending-reminder"', false);
        $this->actingAs($other)->get(route('free-registration.options', $formation))->assertRedirect($this->page($formation));

        // Inscriptions closes.
        $this->travelTo(Carbon::parse('2026-10-11 09:00', 'Africa/Lome'));
        $this->actingAs($other)->get(route('formations.show', $formation))->assertDontSee('data-test="free-pending-reminder"', false);
    }

    public function test_confirming_without_option_shows_a_message_on_the_choice_screen(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();

        $this->actingAs($student)
            ->from(route('free-registration.options', $formation))
            ->post(route('free-registration.store', $formation))
            ->assertRedirect(route('free-registration.options', $formation));

        $this->actingAs($student)->get(route('free-registration.options', $formation))
            ->assertSeeInOrder(['data-test="free-error"', 'Choisissez une option.', 'data-test="free-submit"'], false)
            ->assertSee('Touchez d\'abord une option ci-dessus.', false);

        $this->assertSame(0, FreeRegistration::query()->count());
    }

    public function test_a_hidden_formation_choice_screen_is_only_for_the_admin(): void
    {
        $formation = $this->freeFormation();
        $formation->update(['is_active' => false]);

        $this->actingAs(User::factory()->create())->get(route('free-registration.options', $formation))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get(route('free-registration.options', $formation))
            ->assertOk()
            ->assertSee('Aperçu : l\'inscription fonctionnera une fois la formation publiée.', false);
    }

    public function test_a_formation_without_options_registers_right_after_the_email_confirmation(): void
    {
        $formation = $this->freeFormation(withOptions: false);

        $this->get(route('formations.show', $formation))
            ->assertSee('Confirmez votre inscription : vous êtes inscrit', false)
            ->assertDontSee('data-test="free-options-info"', false);

        $this->get(route('free-registration.start', $formation));
        $student = $this->registerAccount();

        $this->post(route('email-confirmation.confirm'), ['code' => $this->lastMail()->code])
            ->assertRedirect($this->page($formation))
            ->assertSessionHas('status', FreeRegistrationController::REGISTERED);

        $this->assertNull(FreeRegistration::query()->where('user_id', $student->id)->sole()->free_registration_option_id);

        // Compte confirmé sans option à choisir : bouton direct sur la page.
        $this->actingAs(User::factory()->create())->get(route('formations.show', $formation))
            ->assertSee('data-test="free-form"', false)
            ->assertSee('Je confirme mon inscription');
    }

    public function test_ama_and_the_install_banner_never_hide_the_button_or_the_options(): void
    {
        config(['services.gemini.key' => 'cle-de-test', 'services.gemini.model' => 'modele-de-test']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);
        $formation = $this->freeFormation();

        $html = $this->get(route('formations.show', $formation))->getContent();
        $this->assertStringContainsString('<div class="mt-6" data-ama-avoid data-ama-clear data-install-avoid>', $html);
        $this->assertStringContainsString('data-test="free-options-info" data-ama-avoid data-ama-clear data-install-avoid>', $html);
        $this->assertStringContainsString('data-ama-launcher', $html);

        // Écran du choix : aucune bulle d'Ama ni bannière d'installation (pages hors des listes fermées).
        $choice = $this->actingAs(User::factory()->create())->get(route('free-registration.options', $formation))->getContent();
        $this->assertStringNotContainsString('data-ama-launcher', $choice);
        $this->assertStringNotContainsString('data-test="install-banner"', $choice);
        $this->assertStringContainsString('<fieldset data-ama-avoid data-ama-clear data-install-avoid>', $choice);
    }

    public function test_a_paid_formation_is_unchanged(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);

        $this->get(route('free-registration.start', $formation))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('free-registration.options', $formation))->assertNotFound();
        $this->get(route('formations.show', $formation))->assertSee('Acheter maintenant')->assertDontSee('data-test="free-steps"', false);
    }
}
