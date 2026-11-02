<?php

namespace Tests\Feature\FreeRegistrations;

use App\Affiliates\AffiliateAlerts;
use App\Affiliates\AffiliateManager;
use App\Catalogue\CatalogueUpdate;
use App\Enums\EnrollmentSource;
use App\Enums\FreeOptionMode;
use App\Mail\FreeRegistrationMail;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\FreeRegistrationOption;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Inscription gratuite à une formation à 0 FCFA : choix d'une option à places limitées, accès « gratuit »,
 * ajout au groupe, e-mail de confirmation, annulation. Aucun achat, aucune capture.
 */
class FreeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('payment_proofs');
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int}>  $options  [libellé, déroulement, places]
     */
    private function freeFormation(array $options = [['Présentiel journée', 'onsite', 20], ['En ligne soir', 'online', 50]], array $attributes = []): Formation
    {
        $formation = Formation::factory()->create([
            'title' => 'Formation gratuite exemple',
            'price' => 0,
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'Adidogomé, Lycée moderne 2, Lomé',
            ...$attributes,
        ]);

        foreach ($options as $position => [$label, $mode, $capacity]) {
            $formation->freeOptions()->create([
                'label' => $label,
                'schedule' => $mode === 'onsite' ? '9 h à 16 h (pause de 13 h à 14 h)' : '18 h à 20 h (version courte)',
                'mode' => $mode,
                'capacity' => $capacity,
                'position' => $position + 1,
            ]);
        }

        return $formation;
    }

    private function option(Formation $formation, string $label): FreeRegistrationOption
    {
        return $formation->freeOptions()->where('label', $label)->firstOrFail();
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

    public function test_the_page_offers_free_registration_without_any_sale_wording(): void
    {
        // La formation complète créée par la migration de novembre 2026 n'entre pas dans ce test.
        Formation::query()->where('slug', CatalogueUpdate::COMPLETE_SLUG)->delete();
        $formation = $this->freeFormation();

        $response = $this->actingAs(User::factory()->create())->get(route('formations.show', $formation))->assertOk();

        $response->assertSee('Je m\'inscris gratuitement')
            ->assertSee('Gratuit')
            ->assertSee('Présentiel journée')
            ->assertSee('20 places restantes')
            ->assertSee('50 places restantes')
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé')
            ->assertDontSee('Acheter')
            ->assertDontSee('rembours')
            ->assertDontSee('FCFA')
            ->assertDontSee('Accès à vie');

        // Données structurées : aucune offre ni prix de vente.
        $response->assertDontSee('"offers"', false)
            ->assertDontSee('"price"', false)
            ->assertSee('"isAccessibleForFree":true', false);
    }

    public function test_a_visitor_creates_the_account_first_and_sees_the_three_steps(): void
    {
        $formation = $this->freeFormation();

        $response = $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('data-test="free-steps"', false)
            ->assertSeeInOrder(['Créez votre compte', 'Confirmez votre e-mail', 'Choisissez votre option : vous êtes inscrit', 'Créer mon compte', 'J\'ai déjà un compte', 'Présentiel journée'], false)
            ->assertSee('href="'.route('free-registration.start', $formation).'"', false)
            ->assertSessionHas('url.intended', route('formations.show', $formation).'#inscription');

        // Options en simple information : aucun bouton radio sur la page.
        $this->assertStringNotContainsString('type="radio"', $response->getContent());

        // L'inscription elle-même demande toujours un compte.
        $this->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'Présentiel journée')->id])
            ->assertRedirect(route('login'));

        $this->assertSame(0, FreeRegistration::query()->count());
    }

    public function test_the_email_address_must_be_confirmed(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->unverified()->create();

        $this->actingAs($student)->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('data-test="free-unconfirmed"', false)
            ->assertSee('href="'.route('free-registration.start', $formation).'"', false);

        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'Présentiel journée')->id])
            ->assertRedirect(route('email-confirmation.show'));

        $this->assertSame(0, FreeRegistration::query()->count());
        $this->assertFalse($student->isEnrolledIn($formation));
    }

    public function test_registration_opens_free_access_joins_the_group_and_sends_the_email_without_purchase(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();
        $option = $this->option($formation, 'Présentiel journée');

        $this->actingAs($student)
            ->post(route('free-registration.store', $formation), ['option' => $option->id])
            ->assertRedirect(route('formations.show', $formation).'#inscription');

        $registration = FreeRegistration::query()->sole();
        $this->assertSame($option->id, $registration->free_registration_option_id);
        $this->assertTrue($registration->isActive());

        $enrollment = $student->enrollments()->where('formation_id', $formation->id)->sole();
        $this->assertSame(EnrollmentSource::Free, $enrollment->source);
        $this->assertNull($enrollment->purchase_id);
        $this->assertSame(0, Purchase::query()->count());
        $this->assertTrue($this->isInGroup($student, $formation));

        Mail::assertQueued(FreeRegistrationMail::class, fn (FreeRegistrationMail $mail): bool => $mail->hasTo($student->email));

        // Écran après inscription : récapitulatif, groupe, annulation ; une place en moins.
        $this->actingAs($student)->get(route('formations.show', $formation))
            ->assertSee('Vous êtes inscrit(e)')
            ->assertSee('Ouvrir le groupe des inscrits')
            ->assertSee('Annuler mon inscription')
            ->assertSee('9 h à 16 h (pause de 13 h à 14 h)');

        $this->assertSame(19, $option->fresh()->seatsLeft());
    }

    public function test_the_confirmation_email_gives_the_address_or_the_google_meet_sentence(): void
    {
        $formation = $this->freeFormation();
        $onsite = FreeRegistration::query()->create(['formation_id' => $formation->id, 'user_id' => User::factory()->create()->id, 'free_registration_option_id' => $this->option($formation, 'Présentiel journée')->id]);
        $online = FreeRegistration::query()->create(['formation_id' => $formation->id, 'user_id' => User::factory()->create()->id, 'free_registration_option_id' => $this->option($formation, 'En ligne soir')->id]);

        $html = (new FreeRegistrationMail($onsite->load('formation', 'option'), 'Ama'))->render();
        $this->assertStringContainsString('Présentiel journée', $html);
        $this->assertStringContainsString($formation->eventDateLabel(), $html);
        $this->assertStringContainsString('9 h à 16 h (pause de 13 h à 14 h)', $html);
        $this->assertStringContainsString('>Adresse</td>', $html);
        $this->assertStringContainsString('Adidogomé, Lycée moderne 2, Lomé', $html);
        $this->assertStringNotContainsString('Le lien Google Meet sera publié', $html);

        $html = (new FreeRegistrationMail($online->load('formation', 'option'), 'Ama'))->render();
        $this->assertStringContainsString('En ligne soir', $html);
        $this->assertStringContainsString('Le lien Google Meet sera publié dans le groupe des inscrits', $html);
        // L'adresse de l'école figure dans le pied de tous les e-mails : seule la ligne « Adresse » est vérifiée.
        $this->assertStringNotContainsString('>Adresse</td>', $html);
    }

    public function test_one_option_per_person(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'Présentiel journée')->id]);
        $this->actingAs($student)
            ->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'En ligne soir')->id])
            ->assertSessionHasErrors('option');

        $this->assertSame(1, FreeRegistration::query()->count());
        $this->assertSame(0, $this->option($formation, 'En ligne soir')->seatsTaken());
    }

    public function test_an_option_must_be_chosen_among_the_formations_options(): void
    {
        $formation = $this->freeFormation();
        $other = $this->freeFormation([['Autre option', 'onsite', 5]]);
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('free-registration.store', $formation))->assertSessionHasErrors('option');
        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $this->option($other, 'Autre option')->id])->assertSessionHasErrors('option');

        $this->assertSame(0, FreeRegistration::query()->count());
    }

    public function test_a_full_option_is_no_longer_offered_and_cannot_be_taken(): void
    {
        $formation = $this->freeFormation([['Présentiel soir', 'onsite', 2], ['En ligne journée', 'online', 50]]);
        $full = $this->option($formation, 'Présentiel soir');
        $this->fill($full, 2);
        $student = User::factory()->create();

        // Page : l'option pleine reste affichée pour information, marquée « Complet » ; écran du choix : plus proposée.
        $this->actingAs($student)->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSeeInOrder(['Présentiel soir', 'Complet', 'En ligne journée'], false);
        $this->actingAs($student)->get(route('free-registration.options', $formation))
            ->assertOk()
            ->assertDontSee('Présentiel soir')
            ->assertSee('En ligne journée');

        // La dernière place prise entre l'affichage et l'envoi : refus clair, rien n'est créé.
        $this->actingAs($student)
            ->post(route('free-registration.store', $formation), ['option' => $full->id])
            ->assertSessionHasErrors(['option' => 'L\'option « Présentiel soir » vient d\'être complète. Choisissez-en une autre.']);

        $this->assertSame(2, $full->seatsTaken());
        $this->assertFalse($student->isEnrolledIn($formation));
    }

    public function test_the_last_seat_can_only_be_taken_once(): void
    {
        $formation = $this->freeFormation([['Présentiel soir', 'onsite', 1]]);
        $option = $this->option($formation, 'Présentiel soir');
        [$first, $second] = User::factory()->count(2)->create()->all();

        $this->actingAs($first)->post(route('free-registration.store', $formation), ['option' => $option->id])->assertSessionHasNoErrors();
        $this->actingAs($second)->post(route('free-registration.store', $formation), ['option' => $option->id])->assertSessionHasErrors('option');

        $this->assertSame(1, $option->seatsTaken());
        $this->assertFalse($second->isEnrolledIn($formation));
    }

    public function test_everything_full_shows_complet_with_the_whatsapp_number(): void
    {
        $formation = $this->freeFormation([['Présentiel journée', 'onsite', 1], ['En ligne soir', 'online', 1]]);
        $this->fill($this->option($formation, 'Présentiel journée'), 1);
        $this->fill($this->option($formation, 'En ligne soir'), 1);

        $this->actingAs(User::factory()->create())->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('data-test="free-full"', false)
            ->assertSee('Complet')
            ->assertSee('+228 72 24 02 31')
            ->assertSee('wa.me/22872240231', false)
            ->assertDontSee('data-test="free-form"', false);
    }

    public function test_cancelling_frees_the_seat_and_leaves_the_group(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();
        $option = $this->option($formation, 'Présentiel journée');

        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $option->id]);
        $this->assertSame(1, $option->seatsTaken());

        $this->actingAs($student)
            ->delete(route('free-registration.destroy', $formation))
            ->assertRedirect(route('formations.show', $formation).'#inscription');

        $this->assertSame(0, $option->seatsTaken());
        $this->assertFalse($student->fresh()->isEnrolledIn($formation));
        $this->assertFalse($this->isInGroup($student, $formation));
        $this->assertNotNull(FreeRegistration::query()->sole()->cancelled_at);

        // Nouvelle inscription possible, avec une autre option.
        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'En ligne soir')->id])->assertSessionHasNoErrors();

        $this->assertSame(1, FreeRegistration::query()->count());
        $this->assertSame(1, $this->option($formation, 'En ligne soir')->seatsTaken());
        $this->assertTrue($this->isInGroup($student, $formation));
    }

    public function test_cancelling_never_removes_an_access_given_by_the_team(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();
        $student->enroll($formation, EnrollmentSource::Admin);

        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'Présentiel journée')->id]);
        $this->actingAs($student)->delete(route('free-registration.destroy', $formation));

        $this->assertTrue($student->fresh()->isEnrolledIn($formation));
        $this->assertSame(EnrollmentSource::Admin, $student->enrollments()->sole()->source);
    }

    public function test_the_checkout_of_a_free_formation_never_creates_a_purchase(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('checkout.show', $formation))
            ->assertRedirect(route('formations.show', $formation).'#inscription');

        $this->actingAs($student)->post(route('checkout.pay', $formation), [
            'payment_method' => 'tg-mixx',
            'proof' => UploadedFile::fake()->image('capture.png', 600, 1200),
        ])->assertRedirect(route('formations.show', $formation).'#inscription');

        $this->assertSame(0, Purchase::query()->count());
        $this->assertFalse($student->isEnrolledIn($formation));
    }

    public function test_a_formation_without_options_allows_a_simple_registration(): void
    {
        $formation = $this->freeFormation([]);
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('free-registration.store', $formation))->assertSessionHasNoErrors();

        $this->assertNull(FreeRegistration::query()->sole()->free_registration_option_id);
        $this->assertTrue($student->isEnrolledIn($formation));
    }

    public function test_registrations_close_after_the_event_date(): void
    {
        $formation = $this->freeFormation(attributes: ['event_date' => '2026-10-10']);
        $student = User::factory()->create();
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(8, 0));

        $this->actingAs($student)->get(route('formations.show', $formation))->assertSee('Les inscriptions sont closes');
        $this->actingAs($student)
            ->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'Présentiel journée')->id])
            ->assertSessionHasErrors('option');

        $this->assertSame(0, FreeRegistration::query()->count());
    }

    public function test_the_registration_day_itself_is_still_open(): void
    {
        $formation = $this->freeFormation(attributes: ['event_date' => '2026-10-10']);
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(17, 30));

        $this->actingAs(User::factory()->create())
            ->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'En ligne soir')->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FreeRegistration::query()->count());
    }

    public function test_a_draft_free_formation_is_visible_only_by_the_admin_and_closed_to_registration(): void
    {
        $formation = $this->freeFormation(attributes: ['is_active' => false]);
        $option = $this->option($formation, 'Présentiel journée');

        $this->get(route('formations.show', $formation))->assertRedirect(route('formations.index'));
        $this->actingAs(User::factory()->create())->get(route('formations.show', $formation))->assertRedirect(route('formations.index'));

        $this->actingAs(User::factory()->admin()->create())->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('data-test="free-draft-notice"', false)
            ->assertSee('<meta name="robots" content="noindex', false);

        $this->actingAs(User::factory()->create())
            ->post(route('free-registration.store', $formation), ['option' => $option->id])
            ->assertSessionHasErrors('option');

        $this->assertSame(0, FreeRegistration::query()->count());
    }

    public function test_a_hidden_paid_formation_stays_hidden_even_for_the_admin(): void
    {
        $formation = Formation::factory()->inactive()->create(['price' => 15000]);

        // Ses liens circulent encore : ils mènent au catalogue (novembre 2026), même pour l'admin.
        $this->actingAs(User::factory()->admin()->create())->get(route('formations.show', $formation))->assertRedirect(route('formations.index'));
    }

    public function test_free_registrations_do_not_trigger_the_signups_without_purchase_alert(): void
    {
        config(['affiliate.alert_signups_without_purchase' => 2]);
        $formation = $this->freeFormation();
        $affiliate = app(AffiliateManager::class)->activate(User::factory()->create());
        $referrals = User::factory()->count(3)->create(['referred_by_affiliate_id' => $affiliate->id]);

        foreach ($referrals as $referral) {
            $this->actingAs($referral)->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'En ligne soir')->id]);
        }

        $alerts = collect(app(AffiliateAlerts::class)->forAffiliate($affiliate->fresh(), withBalance: false))->pluck('text')->implode(' ');
        $this->assertStringNotContainsString('sans aucun achat', $alerts);

        // Des inscrits sans achat ni inscription gratuite déclenchent toujours l'alerte.
        User::factory()->count(2)->create(['referred_by_affiliate_id' => $affiliate->id]);
        $alerts = collect(app(AffiliateAlerts::class)->forAffiliate($affiliate->fresh(), withBalance: false))->pluck('text')->implode(' ');
        $this->assertStringContainsString('2 inscriptions par son lien sans aucun achat', $alerts);
    }

    public function test_the_catalogue_card_says_free(): void
    {
        $this->freeFormation();

        $this->get(route('formations.index'))->assertOk()->assertSee('Formation gratuite exemple')->assertSee('Gratuit');
    }

    public function test_the_share_image_and_the_student_space_work_for_a_free_formation(): void
    {
        $formation = $this->freeFormation();
        $student = User::factory()->create();
        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $this->option($formation, 'En ligne soir')->id]);

        $this->get(route('og.formation', $formation))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($student)->get(route('student.formations.show', $formation))
            ->assertOk()
            ->assertSee('data-test="student-free-registration"', false)
            ->assertSee('En ligne soir')
            ->assertSee('Ouvrir le groupe des inscrits');
    }

    public function test_the_free_option_mode_labels(): void
    {
        $this->assertSame('Présentiel', FreeOptionMode::Onsite->label());
        $this->assertSame('En ligne (Google Meet)', FreeOptionMode::Online->label());
    }
}
