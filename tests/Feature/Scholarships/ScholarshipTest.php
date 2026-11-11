<?php

namespace Tests\Feature\Scholarships;

use App\Assistant\AmaPrompt;
use App\Enums\AdminAction;
use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Mail\ScholarshipMail;
use App\Models\AdminActivity;
use App\Models\ChatGroupMember;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Payments\PaymentMethods;
use App\Payments\ProofImageProcessor;
use App\Scholarships\ScholarshipCampaign;
use App\Settings\SiteSettings;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Bourse « Créer des agents IA » (novembre 2026) : candidature et paiement des frais SANS compte, premiers payés
 * premiers servis (5 femmes, 5 hommes), compte à rebours et clôture automatique, aucun accès sans la validation de
 * Joseph ; à la validation, compte créé, accès, groupe et e-mail avec lien pour choisir le mot de passe.
 */
class ScholarshipTest extends TestCase
{
    use RefreshDatabase;

    private Formation $formation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake(ProofImageProcessor::DISK);
        // Les nombreux envois des tests dépasseraient la limite par adresse IP (testée à part).
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->travelTo(now('Africa/Lome')->setDate(2026, 11, 11)->setTime(16, 0));
        $this->formation = Formation::factory()->create(['slug' => 'creation-agents-ia', 'title' => 'Créer des agents IA', 'price' => 150000]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->open();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function open(array $overrides = []): void
    {
        SiteSettings::save(['site.scholarship' => [
            'enabled' => true,
            'campaign' => 'agents-ia-test',
            'formation' => 'creation-agents-ia',
            'opens_at' => '2026-11-11 15:00',
            'closes_at' => '2026-11-12 15:00',
            'fee' => 5500,
            'value' => 150000,
            'seats_per_gender' => 5,
            ...$overrides,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        static $count = 0;
        $count++;

        return [
            'name' => 'Afi Mensah',
            'phone' => '+228 90 12 34 '.str_pad((string) ($count % 100), 2, '0', STR_PAD_LEFT),
            'email' => 'afi'.$count.'@example.com',
            'gender' => 'female',
            'age' => 25,
            'city' => 'Lomé, Togo',
            'level' => 'uses_ai',
            'tools' => 'ChatGPT, Canva',
            'device' => 'computer',
            'motivation' => 'Je veux automatiser le service client de ma petite boutique en ligne et aider mon quartier.',
            'first_agent' => 'Un agent qui répond aux clients de ma boutique sur WhatsApp.',
            'available' => 'yes',
            'accepts_agents' => '1',
            'accepts_video' => '1',
            'accepts_terms' => '1',
            'payment_method' => PaymentMethods::enabled()->keys()->first(),
            'proof' => UploadedFile::fake()->image('capture-'.$count.'.png', 400 + $count, 800),
            ...$overrides,
        ];
    }

    private function apply(array $overrides = []): ScholarshipApplication
    {
        $this->post(route('scholarship.store'), $this->form($overrides))->assertSessionHasNoErrors();

        return ScholarshipApplication::query()->latest('id')->firstOrFail();
    }

    public function test_the_page_is_hidden_while_off_and_shows_the_offer_the_counter_and_the_form_when_open(): void
    {
        $this->open(['enabled' => false]);
        $this->get(route('scholarship'))->assertNotFound();
        $this->getJson(route('scholarship.places'))->assertNotFound();
        $this->actingAs($this->admin)->get(route('scholarship'))->assertOk()->assertSee('Aperçu réservé à l\'admin', false);
        auth()->logout();

        $this->open();
        $this->get(route('scholarship'))->assertOk()
            ->assertSee("10 bourses à 100\u{00A0}% — Formation «\u{00A0}Créer des agents IA\u{00A0}»", false)
            ->assertSee(Money::fcfa(150000))
            ->assertSee(Money::fcfa(5500))
            ->assertSee('Avoir entre 20 et 40 ans.')
            ->assertSee('Si tu n\'es pas retenu(e), tes '.Money::fcfa(5500).' te sont remboursés.', false)
            ->assertSee('Pourquoi veux-tu apprendre à créer des agents IA ? Quel est ton grand « pourquoi » ?', false)
            ->assertSee('data-test="scholarship-counter"', false)
            ->assertSee('data-test="scholarship-form"', false)
            ->assertSee('Premiers payés, premiers servis')
            // Aucune promesse de revenus ni d'emploi.
            ->assertDontSee('revenu')
            ->assertDontSee('emploi');

        $this->getJson(route('scholarship.places'))->assertOk()
            ->assertJson(['state' => 'open', 'remaining' => ['female' => 5, 'male' => 5], 'total' => 10]);
    }

    public function test_a_visitor_applies_and_pays_without_an_account_and_gets_no_access(): void
    {
        $this->post(route('scholarship.store'), $this->form(['email' => 'Kossi@Example.com', 'gender' => 'male', 'name' => 'Kossi Agbo']))
            ->assertRedirect(route('scholarship').'#candidature')
            ->assertSessionHas('scholarship_sent', 'Kossi');

        $application = ScholarshipApplication::query()->sole();
        $this->assertSame(ScholarshipApplication::RESERVED, $application->status);
        $this->assertSame('kossi@example.com', $application->email);
        $this->assertSame(5500, $application->amount);
        $this->assertStringStartsWith('bourses/', $application->proof_path);
        Storage::disk(ProofImageProcessor::DISK)->assertExists($application->proof_path);

        // Aucun compte, aucun achat, aucun accès avant la validation de Joseph.
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Purchase::query()->count());

        Mail::assertSent(ScholarshipMail::class, fn (ScholarshipMail $mail): bool => $mail->kind === ScholarshipMail::RECEIVED && $mail->hasTo('kossi@example.com'));
        $this->get(route('scholarship'))->assertSee('Candidature envoyée, Kossi !', false);
        $this->getJson(route('scholarship.places'))->assertJson(['remaining' => ['female' => 5, 'male' => 4], 'total' => 9]);
    }

    public function test_the_form_checks_age_answers_duplicates_and_reused_captures(): void
    {
        $this->post(route('scholarship.store'), $this->form(['age' => 19]))->assertSessionHasErrors(['age' => 'La bourse est réservée aux personnes de 20 à 40 ans.']);
        $this->post(route('scholarship.store'), $this->form(['age' => 41]))->assertSessionHasErrors('age');
        $this->post(route('scholarship.store'), $this->form(['accepts_agents' => null, 'accepts_video' => null, 'accepts_terms' => null, 'motivation' => 'court', 'gender' => 'autre']))
            ->assertSessionHasErrors(['accepts_agents', 'accepts_video', 'accepts_terms', 'motivation', 'gender']);
        $this->post(route('scholarship.store'), $this->form(['phone' => '12']))->assertSessionHasErrors('phone');
        $this->post(route('scholarship.store'), $this->form(['proof' => UploadedFile::fake()->create('preuve.pdf', 100, 'application/pdf')]))->assertSessionHasErrors('proof');
        $this->assertSame(0, ScholarshipApplication::query()->count());

        $proof = UploadedFile::fake()->image('meme.png', 500, 900);
        $first = $this->apply(['email' => 'ama@example.com', 'phone' => '+228 91 00 00 01', 'proof' => $proof]);

        // Même adresse, même numéro, même capture : refusés.
        $this->post(route('scholarship.store'), $this->form(['email' => 'ama@example.com']))->assertSessionHasErrors('email');
        $this->post(route('scholarship.store'), $this->form(['phone' => '+228 91 00 00 01']))->assertSessionHasErrors('phone');
        $this->post(route('scholarship.store'), $this->form(['proof' => UploadedFile::fake()->createWithContent('copie.png', (string) file_get_contents($proof->getRealPath()))]))
            ->assertSessionHasErrors('proof');

        // Champ piège : réponse neutre, rien d'enregistré.
        $this->post(route('scholarship.store'), $this->form(['website' => 'robot']))->assertSessionHas('scholarship_sent');
        $this->assertSame([$first->id], ScholarshipApplication::query()->pluck('id')->all());
    }

    public function test_places_are_first_paid_first_served_and_close_automatically(): void
    {
        foreach (range(1, 5) as $i) {
            $this->apply(['gender' => 'female']);
        }

        // Plus de place pour les femmes : refus, et le choix est désactivé sur la page.
        $this->post(route('scholarship.store'), $this->form(['gender' => 'female']))->assertSessionHasErrors('gender');
        $this->get(route('scholarship'))->assertSee('Plus de place');

        foreach (range(1, 5) as $i) {
            $this->apply(['gender' => 'male']);
        }

        $this->assertSame(ScholarshipCampaign::FULL, ScholarshipCampaign::state());
        $this->get(route('scholarship'))->assertSee('Toutes les places sont prises')->assertDontSee('data-test="scholarship-form"', false);
        $this->post(route('scholarship.store'), $this->form(['gender' => 'male']))->assertSessionHasErrors('form');

        // Une capture refusée (paiement introuvable) libère la place.
        $refused = ScholarshipApplication::query()->where('gender', 'female')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.scholarship.refuse', $refused), ['refund' => '0'])->assertRedirect();
        auth()->logout();
        $this->assertSame(['female' => 1, 'male' => 0], ScholarshipCampaign::remaining());
        $this->apply(['gender' => 'female']);
        $this->assertSame(ScholarshipCampaign::FULL, ScholarshipCampaign::state());
    }

    public function test_applications_are_refused_before_the_opening_and_after_the_closing(): void
    {
        $this->open(['opens_at' => '2026-11-11 18:00']);
        $this->get(route('scholarship'))->assertSee('Les candidatures ne sont pas encore ouvertes');
        $this->post(route('scholarship.store'), $this->form())->assertSessionHasErrors('form');

        $this->open();
        $this->travelTo(now('Africa/Lome')->setDate(2026, 11, 12)->setTime(15, 0));
        $this->assertSame(ScholarshipCampaign::CLOSED, ScholarshipCampaign::state());
        $this->get(route('scholarship'))->assertSee('Candidatures closes');
        $this->post(route('scholarship.store'), $this->form())->assertSessionHasErrors('form');
        $this->assertSame(0, ScholarshipApplication::query()->count());
    }

    public function test_validation_creates_the_account_opens_the_formation_and_the_group_and_sends_the_access_link(): void
    {
        $application = $this->apply(['name' => 'Yao Koffi', 'email' => 'yao@example.com', 'gender' => 'male', 'phone' => '+228 92 00 00 02']);

        $this->actingAs($this->admin)->get(route('admin.scholarship.index'))->assertOk()
            ->assertSee('Yao Koffi')
            ->assertSee('Validées 0 / 5')
            ->assertSee('Je veux automatiser le service client');
        $this->actingAs($this->admin)->get(route('admin.scholarship.proof', $application))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.scholarship.validate', $application))->assertRedirect()->assertSessionHas('status');

        $application->refresh();
        $user = User::query()->where('email', 'yao@example.com')->sole();
        $this->assertTrue($application->isValidated());
        $this->assertSame($user->id, $application->user_id);
        $this->assertSame('Yao Koffi', $user->name);
        $this->assertSame('+22892000002', $user->phone);
        $this->assertTrue($user->hasConfirmedEmail());

        $purchase = Purchase::query()->sole();
        $this->assertSame(PurchaseStatus::Validated, $purchase->status);
        $this->assertSame(5500, $purchase->amount);
        $this->assertSame($user->id, $purchase->user_id);
        $this->assertTrue($user->isEnrolledIn($this->formation));
        $this->assertSame(EnrollmentSource::Purchase, $user->enrollments()->sole()->source);
        $this->assertTrue(ChatGroupMember::query()->where('user_id', $user->id)->where('chat_group_id', $this->formation->chatGroup->id)->whereNull('removed_at')->exists());
        $this->assertSame(1, AdminActivity::query()->where('action', AdminAction::ScholarshipValidated)->count());

        Mail::assertSent(ScholarshipMail::class, function (ScholarshipMail $mail): bool {
            if ($mail->kind !== ScholarshipMail::VALIDATED) {
                return false;
            }
            $html = $mail->render();

            return $mail->hasTo('yao@example.com') && str_contains($html, 'Choisir mon mot de passe') && str_contains($html, 'bourse-agents-ia/acces/');
        });

        // Lien de l'e-mail : page « Choisir mon mot de passe » ; un lien modifié ne fonctionne pas.
        $link = URL::temporarySignedRoute('scholarship.access', now()->addDays(7), ['application' => $application->id]);
        auth()->logout();
        $this->get($link)->assertRedirect()->assertRedirectContains('/reset-password/');
        $this->get(route('scholarship.access', $application))->assertForbidden();

        // Message WhatsApp prêt à envoyer ; une seconde validation est impossible.
        $this->actingAs($this->admin)->get(route('admin.scholarship.index', ['statut' => 'validees']))->assertSee('wa.me/22892000002?text=');
        $this->actingAs($this->admin)->post(route('admin.scholarship.validate', $application))->assertSessionHasErrors('decision');
        $this->assertSame(1, Purchase::query()->count());
    }

    public function test_validation_links_an_existing_account_with_the_same_email(): void
    {
        $existing = User::factory()->create(['email' => 'mawu@example.com', 'name' => 'Mawu Ancienne']);
        $application = $this->apply(['email' => 'MAWU@example.com']);

        $this->actingAs($this->admin)->post(route('admin.scholarship.validate', $application))->assertRedirect();

        $this->assertSame($existing->id, $application->refresh()->user_id);
        $this->assertSame(1, User::query()->where('email', 'mawu@example.com')->count());
        $this->assertTrue($existing->isEnrolledIn($this->formation));
        Mail::assertSent(ScholarshipMail::class, fn (ScholarshipMail $mail): bool => $mail->kind === ScholarshipMail::VALIDATED && str_contains($mail->render(), 'Me connecter'));
    }

    public function test_refusals_free_the_place_and_refunds_are_tracked(): void
    {
        $unpaid = $this->apply();
        $paid = $this->apply();

        $this->actingAs($this->admin)->post(route('admin.scholarship.refuse', $unpaid), ['refund' => '0'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.scholarship.refuse', $paid), ['refund' => '1'])->assertRedirect();

        $this->assertFalse($unpaid->refresh()->refund_due);
        $this->assertTrue($paid->refresh()->refund_due);
        $this->assertSame(0, User::query()->where('email', $paid->email)->count());
        Mail::assertSent(ScholarshipMail::class, fn (ScholarshipMail $mail): bool => $mail->kind === ScholarshipMail::REFUSED_UNPAID && $mail->hasTo($unpaid->email));
        Mail::assertSent(ScholarshipMail::class, fn (ScholarshipMail $mail): bool => $mail->kind === ScholarshipMail::REFUSED_REFUND && $mail->hasTo($paid->email));

        $this->actingAs($this->admin)->get(route('admin.scholarship.index', ['statut' => 'a-rembourser']))->assertSee($paid->email)->assertDontSee($unpaid->email);
        $this->actingAs($this->admin)->post(route('admin.scholarship.refunded', $paid))->assertRedirect();
        $this->assertNotNull($paid->refresh()->refunded_at);
        $this->actingAs($this->admin)->post(route('admin.scholarship.refunded', $unpaid))->assertSessionHasErrors('decision');
    }

    public function test_the_admin_filters_and_saves_the_settings_and_only_the_admin_gets_in(): void
    {
        $this->apply(['gender' => 'female', 'age' => 22, 'level' => 'beginner', 'name' => 'Afi Jeune']);
        $this->apply(['gender' => 'male', 'age' => 38, 'level' => 'built', 'name' => 'Kodjo Expert']);

        $this->actingAs($this->admin)->get(route('admin.scholarship.index', ['genre' => 'female']))->assertSee('Afi Jeune')->assertDontSee('Kodjo Expert');
        $this->actingAs($this->admin)->get(route('admin.scholarship.index', ['age' => '36-40']))->assertSee('Kodjo Expert')->assertDontSee('Afi Jeune');
        $this->actingAs($this->admin)->get(route('admin.scholarship.index', ['niveau' => 'beginner']))->assertSee('Afi Jeune')->assertDontSee('Kodjo Expert');

        $this->actingAs($this->admin)->put(route('admin.scholarship.settings'), [
            'enabled' => '1', 'formation' => 'creation-agents-ia', 'opens_at' => '2026-11-11T17:30', 'closes_at' => '2026-11-12T17:30',
            'fee' => 5500, 'value' => 150000, 'seats_per_gender' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-11-12 17:30', config('site.scholarship.closes_at'));
        $this->assertSame(1, AdminActivity::query()->where('action', AdminAction::ScholarshipSettingsUpdated)->count());

        $this->actingAs($this->admin)->put(route('admin.scholarship.settings'), [
            'enabled' => '1', 'formation' => 'creation-agents-ia', 'opens_at' => '2026-11-12T17:30', 'closes_at' => '2026-11-11T17:30',
            'fee' => 5500, 'value' => 150000, 'seats_per_gender' => 5,
        ])->assertSessionHasErrors('closes_at');

        $student = User::factory()->create();
        $this->actingAs($student)->get(route('admin.scholarship.index'))->assertForbidden();
        $application = ScholarshipApplication::query()->firstOrFail();
        $this->actingAs($student)->get(route('admin.scholarship.proof', $application))->assertForbidden();
        $this->actingAs($student)->post(route('admin.scholarship.validate', $application))->assertForbidden();
    }

    public function test_applications_are_limited_per_ip_address(): void
    {
        $this->withMiddleware(ThrottleRequests::class);

        foreach (range(1, 20) as $i) {
            $this->post(route('scholarship.store'), ['age' => 19])->assertSessionHasErrors();
        }

        $this->post(route('scholarship.store'), $this->form())->assertStatus(429);
    }

    public function test_ama_knows_the_scholarship_with_live_places_and_never_when_it_is_off(): void
    {
        $this->apply(['gender' => 'male']);

        $prompt = AmaPrompt::build(['page' => 'page de la bourse']);
        $this->assertStringContainsString('### Bourse « Créer des agents IA »', $prompt);
        $this->assertStringContainsString('Places restantes en ce moment : 5 pour les femmes et 4 pour les hommes', $prompt);
        $this->assertStringContainsString('candidatures ouvertes jusqu\'au jeudi 12 novembre 2026 à 15 h 00', $prompt);
        $this->assertStringContainsString('ses '.Money::fcfa(5500).' lui sont remboursés', $prompt);
        $this->assertStringContainsString('tu ne réserves ni ne valides jamais une place ni un paiement', $prompt);

        $this->open(['enabled' => false]);
        $this->assertStringNotContainsString('Bourse « Créer des agents IA »', AmaPrompt::build([]));
    }

    public function test_the_page_opens_on_a_call_to_action_and_a_step_by_step_form(): void
    {
        $this->open();

        $page = $this->get(route('scholarship'))->assertOk();
        $content = $page->getContent();

        // Le bouton principal vient avant tout le reste de la page (premier écran).
        $page->assertSee('data-test="scholarship-apply"', false)->assertSee('Je postule maintenant');
        $this->assertLessThan(strpos($content, 'data-test="scholarship-offer"'), strpos($content, 'data-test="scholarship-apply"'));

        // Une question par écran : 13 questions puis 4 étapes de paiement, dans un seul formulaire.
        $this->assertSame(17, preg_match_all('/data-step="\d+"/', $content));
        $page->assertSee('Question 1 sur 13')->assertSee('Paiement · étape 4 sur 4')
            ->assertSee('data-test="scholarship-next"', false)
            ->assertSee('data-test="scholarship-submit"', false)
            ->assertSee('scholarshipApply(', false);
        $this->assertSame(1, substr_count($content, 'data-test="scholarship-form"'));

        // Condition principale mise en avant, avant ce que reçoit l'élève, et case obligatoire dans le formulaire.
        $page->assertSee('data-test="scholarship-main-condition"', false)
            ->assertSee('3 agents IA pour Coach Joseph')
            ->assertSee('name="accepts_agents"', false);
        $this->assertLessThan(strpos($content, 'data-test="scholarship-offer"'), strpos($content, 'data-test="scholarship-main-condition"'));
    }

    public function test_a_refused_answer_reopens_the_form_on_the_question_to_fix(): void
    {
        $this->open();

        $this->from(route('scholarship'))
            ->post(route('scholarship.store'), $this->form(['age' => '45']))
            ->assertSessionHasErrors('age');

        $content = $this->get(route('scholarship'))->getContent();
        $this->assertStringContainsString('&quot;autoOpen&quot;:true', $content);
        $this->assertMatchesRegularExpression('/&quot;step&quot;:4,/', $content);
    }

    public function test_the_scholarship_is_promoted_on_the_home_page_and_in_the_menu_only_while_open(): void
    {
        $this->open();
        $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-scholarship"', false)
            ->assertSee('plus que 10 places')
            ->assertSee('data-test="menu-scholarship"', false);

        $this->open(['enabled' => false]);
        $this->get(route('home'))->assertOk()
            ->assertDontSee('data-test="home-scholarship"', false)
            ->assertDontSee('data-test="menu-scholarship"', false);
    }
}
