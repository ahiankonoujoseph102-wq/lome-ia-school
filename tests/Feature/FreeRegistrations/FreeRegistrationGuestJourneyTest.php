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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ANCIEN parcours d'un visiteur sans compte (option choisie avant le compte), remplacé en octobre 2026 par
 * « compte d'abord » (FreeRegistrationAccountFirstTest) mais toujours pris en charge pour les personnes qui
 * avaient déjà fait leur choix (choix gardé en mémoire, page déjà ouverte) : création du compte (rappel de son
 * choix), confirmation de l'e-mail par code, puis inscription terminée toute seule (place prise à ce moment-là,
 * entrée dans le groupe, écran de confirmation). Option devenue pleine entre-temps : écran du choix parmi les
 * options restantes, avec un message clair.
 */
class FreeRegistrationGuestJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-04 10:00', 'Africa/Lome'));
    }

    private function freeFormation(int $onsiteCapacity = 20): Formation
    {
        $formation = Formation::factory()->create([
            'title' => 'Formation gratuite exemple',
            'price' => 0,
            'event_date' => '2026-10-10',
            'event_location' => 'Adidogomé, Lycée moderne 2, Lomé',
        ]);

        $formation->freeOptions()->create(['label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => $onsiteCapacity, 'position' => 1]);
        $formation->freeOptions()->create(['label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50, 'position' => 2]);

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

    public function test_a_guest_chooses_creates_an_account_confirms_the_code_and_is_registered_automatically(): void
    {
        $formation = $this->freeFormation();
        $option = $this->option($formation, 'Présentiel journée');

        // 1. Choix de l'option, sans compte.
        $this->get(route('formations.show', $formation))->assertOk()->assertSee('data-test="free-steps"', false);
        $this->post(route('free-registration.choose', $formation), ['option' => $option->id, 'then' => 'register'])
            ->assertRedirect(route('register'))
            ->assertSessionHas(PendingFreeChoice::SESSION_KEY, ['formation_id' => $formation->id, 'option_id' => $option->id]);

        // 2. Création du compte, avec le rappel du choix en haut.
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('data-test="free-choice-reminder"', false)
            ->assertSee('Vous vous inscrivez à la formation gratuite du 10 octobre, option : Présentiel journée.', false)
            ->assertSee('Changer d\'option', false);

        $student = $this->registerAccount();

        // Aucune place réservée avant la fin de l'inscription.
        $this->assertSame(0, FreeRegistration::query()->count());
        $this->assertSame(20, $option->fresh()->seatsLeft());

        // 3. Confirmation de l'e-mail : rappel, puis code.
        $this->get(route('email-confirmation.show'))
            ->assertOk()
            ->assertSee('Vous vous inscrivez à la formation gratuite du 10 octobre, option : Présentiel journée.', false)
            ->assertSee('Dernière étape : saisissez le code, vous serez inscrit(e) aussitôt.', false);

        $this->post(route('email-confirmation.confirm'), ['code' => $this->lastMail()->code])
            ->assertRedirect($this->page($formation))
            ->assertSessionHas('status', FreeRegistrationController::REGISTERED);

        // Inscrit(e) : place prise, accès « gratuit », groupe, e-mail de confirmation ; choix oublié.
        $registration = FreeRegistration::query()->sole();
        $this->assertSame($student->id, $registration->user_id);
        $this->assertSame($option->id, $registration->free_registration_option_id);
        $this->assertSame(EnrollmentSource::Free, $student->enrollments()->where('formation_id', $formation->id)->sole()->source);
        $this->assertTrue($this->isInGroup($student, $formation));
        $this->assertSame(19, $option->fresh()->seatsLeft());
        Mail::assertQueued(FreeRegistrationMail::class, fn (FreeRegistrationMail $mail): bool => $mail->hasTo('afi@example.com'));
        $this->assertNull(session(PendingFreeChoice::SESSION_KEY));

        // Écran final, sans revenir choisir.
        $this->get(route('formations.show', $formation))
            ->assertSee('data-test="free-registered"', false)
            ->assertSee('Vous êtes inscrit(e)')
            ->assertSee('Ouvrir le groupe des inscrits')
            ->assertDontSee('data-test="free-choice-reminder"', false);
    }

    public function test_an_old_form_sent_without_option_shows_a_message_on_the_page(): void
    {
        $formation = $this->freeFormation();

        // Page ouverte avant la mise à jour, sans option choisie : le serveur renvoie un message clair.
        $this->post(route('free-registration.choose', $formation), ['then' => 'register'])
            ->assertRedirect($this->page($formation));

        $this->get(route('formations.show', $formation))
            ->assertSeeInOrder(['data-test="free-error"', 'Choisissez d&#039;abord une option ci-dessus', 'data-test="free-create-account"'], false);

        $this->assertNull(session(PendingFreeChoice::SESSION_KEY));
    }

    public function test_an_option_that_became_full_brings_the_person_back_with_a_clear_message(): void
    {
        $formation = $this->freeFormation(onsiteCapacity: 1);
        $full = $this->option($formation, 'Présentiel journée');
        $other = $this->option($formation, 'En ligne soir');

        $this->post(route('free-registration.choose', $formation), ['option' => $full->id, 'then' => 'register']);
        $student = $this->registerAccount();

        // Quelqu'un prend la dernière place pendant que la personne lit son e-mail.
        $this->fill($full, 1);

        $this->post(route('email-confirmation.confirm'), ['code' => $this->lastMail()->code])
            ->assertRedirect(route('free-registration.options', $formation));

        $this->assertTrue($student->fresh()->hasConfirmedEmail());
        $this->assertFalse(FreeRegistration::query()->where('user_id', $student->id)->exists());
        $this->assertFalse($student->isEnrolledIn($formation));
        $this->assertSame(1, $full->fresh()->seatsTaken());

        // Écran du choix : message clair, seule l'option restante est proposée, rien n'est présélectionné.
        $page = $this->get(route('free-registration.options', $formation))
            ->assertSee('data-test="free-error"', false)
            ->assertSee('L&#039;option « Présentiel journée » vient d&#039;être complète. Choisissez-en une autre.', false)
            ->assertSee('value="'.$other->id.'"', false)
            ->assertDontSee('value="'.$full->id.'"', false);
        $this->assertDoesNotMatchRegularExpression('/<input type="radio"[^>]*\schecked/', $page->getContent());

        // Elle choisit l'option restante et s'inscrit.
        $this->post(route('free-registration.store', $formation), ['option' => $other->id])->assertRedirect($this->page($formation));
        $this->assertSame($other->id, FreeRegistration::query()->where('user_id', $student->id)->sole()->free_registration_option_id);
        $this->assertSame(1, $full->fresh()->seatsTaken());
    }

    public function test_a_full_option_is_refused_at_once_when_choosing(): void
    {
        $formation = $this->freeFormation(onsiteCapacity: 1);
        $full = $this->option($formation, 'Présentiel journée');
        $this->fill($full, 1);

        $this->post(route('free-registration.choose', $formation), ['option' => $full->id, 'then' => 'register'])
            ->assertRedirect($this->page($formation))
            ->assertSessionHasErrors('option')
            ->assertSessionMissing(PendingFreeChoice::SESSION_KEY);
    }

    public function test_i_already_have_an_account_logs_in_and_keeps_the_choice(): void
    {
        $formation = $this->freeFormation();
        $option = $this->option($formation, 'En ligne soir');
        $student = User::factory()->create(['email' => 'kossi@example.com']);

        $this->post(route('free-registration.choose', $formation), ['option' => $option->id, 'then' => 'login'])
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertSee('Vous vous inscrivez à la formation gratuite du 10 octobre, option : En ligne soir.', false)
            ->assertSee('Connectez-vous : votre inscription se terminera juste après.', false);

        $this->post(route('login.store'), ['email' => 'kossi@example.com', 'password' => 'password'])
            ->assertRedirect(route('free-registration.complete'));

        $this->get(route('free-registration.complete'))
            ->assertRedirect($this->page($formation))
            ->assertSessionHas('status', FreeRegistrationController::REGISTERED);

        $this->assertSame($option->id, FreeRegistration::query()->where('user_id', $student->id)->sole()->free_registration_option_id);
        $this->assertTrue($this->isInGroup($student, $formation));
    }

    public function test_i_already_have_an_account_without_option_returns_to_the_page(): void
    {
        $formation = $this->freeFormation();

        $this->post(route('free-registration.choose', $formation), ['then' => 'login'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $this->page($formation))
            ->assertSessionMissing(PendingFreeChoice::SESSION_KEY);
    }

    public function test_a_logged_in_student_with_an_unconfirmed_address_is_led_to_the_confirmation_then_registered(): void
    {
        $formation = $this->freeFormation();
        $option = $this->option($formation, 'Présentiel journée');
        $student = User::factory()->unverified()->create(['email' => 'ama@example.com']);
        $this->actingAs($student);

        $this->post(route('free-registration.choose', $formation), ['option' => $option->id, 'then' => 'register'])
            ->assertRedirect(route('free-registration.complete'));

        $this->get(route('free-registration.complete'))->assertRedirect(route('email-confirmation.show'));
        $this->assertSame(0, FreeRegistration::query()->count());

        $this->get(route('email-confirmation.show'))->assertSee('option : Présentiel journée.', false);
        $this->post(route('email-confirmation.resend'));

        $this->post(route('email-confirmation.confirm'), ['code' => $this->lastMail('ama@example.com')->code])
            ->assertRedirect($this->page($formation));

        $this->assertSame($option->id, FreeRegistration::query()->where('user_id', $student->id)->sole()->free_registration_option_id);
        $this->assertTrue($this->isInGroup($student, $formation));
    }

    public function test_the_email_button_opened_on_another_device_still_completes_the_registration(): void
    {
        $formation = $this->freeFormation();
        $option = $this->option($formation, 'Présentiel journée');

        $this->post(route('free-registration.choose', $formation), ['option' => $option->id, 'then' => 'register']);
        $student = $this->registerAccount();
        $this->get(route('email-confirmation.show'));
        $token = $this->lastMail()->token;

        // Autre appareil : aucune session, personne de connecté.
        Auth::logout();
        $this->flushSession();

        $this->get(route('email-confirmation.link', $token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $this->page($formation));

        $this->assertSame($option->id, FreeRegistration::query()->where('user_id', $student->id)->sole()->free_registration_option_id);
        $this->assertTrue($this->isInGroup($student, $formation));
    }

    public function test_people_already_registered_are_not_affected(): void
    {
        $formation = $this->freeFormation();
        $option = $this->option($formation, 'Présentiel journée');
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $option->id]);

        // Un ancien choix d'une autre option ne change rien à une inscription existante.
        $this->actingAs($student)->get(route('free-registration.complete'))->assertRedirect(route('dashboard'));
        $this->actingAs($student)->post(route('free-registration.choose', $formation), ['option' => $this->option($formation, 'En ligne soir')->id])
            ->assertRedirect($this->page($formation));

        $this->assertSame($option->id, FreeRegistration::query()->sole()->free_registration_option_id);
        $this->actingAs($student)->get(route('formations.show', $formation))->assertSee('Vous êtes inscrit(e)');
    }

    public function test_a_paid_formation_is_unchanged(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);

        $this->post(route('free-registration.choose', $formation), ['option' => 1])->assertNotFound();
        $this->get(route('formations.show', $formation))->assertSee('Acheter maintenant')->assertDontSee('data-test="free-steps"', false);
    }
}
