<?php

namespace Tests\Feature\Payments;

use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Events\FormationAccessGranted;
use App\Events\FormationAccessRevoked;
use App\Events\PaymentValidated;
use App\Mail\NewPaymentToVerifyMail;
use App\Mail\PaymentProofReceivedMail;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentValidatedMail;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');
        Mail::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function proof(array $overrides = [], int $width = 600, int $height = 1200): array
    {
        return array_merge([
            'payment_method' => 'tg-mixx',
            'proof' => UploadedFile::fake()->image('capture.png', $width, $height),
        ], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_price_below_threshold_gives_immediate_provisional_access(): void
    {
        Event::fake([FormationAccessGranted::class]);
        $student = User::factory()->create(['name' => 'Afi Dossou']);
        $formation = Formation::factory()->create(['price' => 5500]);

        $response = $this->actingAs($student)->post(route('checkout.pay', $formation), $this->proof());

        $purchase = Purchase::firstOrFail();
        $response->assertRedirect(route('student.purchases.show', $purchase));

        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->assertSame(5500, $purchase->amount);
        // Seule la capture est envoyée : numéro, nom et identifiant se lisent dessus.
        $this->assertNull($purchase->payer_phone);
        $this->assertNull($purchase->payer_name);
        $this->assertNull($purchase->transaction_id);
        $this->assertSame('Mixx by Yas', $purchase->payment_method_name);
        $this->assertTrue($student->isEnrolledIn($formation));
        $this->assertSame($purchase->id, $student->enrollments()->first()?->purchase_id);
        Storage::disk('payment_proofs')->assertExists((string) $purchase->proof_path);
        Event::assertDispatched(FormationAccessGranted::class);

        Mail::assertQueued(PaymentProofReceivedMail::class, fn (PaymentProofReceivedMail $mail): bool => $mail->hasTo($student->email));
        Mail::assertQueued(NewPaymentToVerifyMail::class, fn (NewPaymentToVerifyMail $mail): bool => $mail->hasTo(config('site.payments.admin_email')));

        $this->actingAs($student)->get(route('student.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Votre paiement sera vérifié sous peu.');
        $this->actingAs($student)->get(route('student.formations.show', $formation))->assertOk();
    }

    public function test_price_at_or_above_threshold_waits_for_validation_without_access(): void
    {
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 50000]);

        $this->actingAs($student)->post(route('checkout.pay', $formation), $this->proof());

        $purchase = Purchase::firstOrFail();
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertFalse($student->isEnrolledIn($formation));

        $this->actingAs($student)->get(route('student.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Paiement reçu, en attente de validation')
            ->assertSee('du lundi au samedi, de 8 h à 20 h');
        $this->actingAs($student)->get(route('student.formations.show', $formation))->assertForbidden();
    }

    public function test_only_the_capture_is_needed_and_old_fields_are_ignored(): void
    {
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 5500]);

        $this->actingAs($student)
            ->post(route('checkout.pay', $formation), $this->proof([
                'payer_phone' => '90 12 34 56',
                'payer_name' => 'Afi Dossou',
                'transaction_id' => 'MP240925.1001.A12345',
            ]))
            ->assertSessionHasNoErrors();

        $purchase = Purchase::firstOrFail();
        $this->assertNull($purchase->payer_phone);
        $this->assertNull($purchase->transaction_id);

        // Deux paiements sans identifiant de transaction ne se gênent pas (index unique sur une valeur vide).
        $other = User::factory()->create();
        $this->actingAs($other)
            ->post(route('checkout.pay', $formation), $this->proof([], 700, 1300))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Purchase::count());
    }

    public function test_capture_already_sent_is_refused(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 5500]);

        $this->actingAs($first)->post(route('checkout.pay', $formation), $this->proof())->assertSessionHasNoErrors();

        // Même image (mêmes octets), envoyée par un autre élève.
        $this->actingAs($second)
            ->post(route('checkout.pay', $formation), $this->proof())
            ->assertSessionHasErrors(['proof' => 'Cette capture d\'écran a déjà été envoyée. Envoyez la capture de votre propre paiement.']);

        $this->assertFalse($second->isEnrolledIn($formation));
        $this->assertSame(1, Purchase::count());
    }

    public function test_only_one_unverified_payment_at_a_time(): void
    {
        $student = User::factory()->create();
        [$first, $second] = Formation::factory()->count(2)->create(['price' => 5500]);

        $this->actingAs($student)->post(route('checkout.pay', $first), $this->proof())->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->post(route('checkout.pay', $second), $this->proof([], 700, 1300))
            ->assertSessionHasErrors('formation');

        $this->assertFalse($student->isEnrolledIn($second));
        $this->assertSame(1, Purchase::count());

        $this->actingAs($student)->get(route('checkout.show', $second))
            ->assertOk()
            ->assertSee('Un paiement est déjà en cours de vérification')
            ->assertDontSee('Envoyer la capture de mon paiement');
    }

    public function test_formation_already_owned_cannot_be_bought_again(): void
    {
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 5500]);
        Purchase::factory()->for($student)->for($formation)->status(PurchaseStatus::Validated)->create();
        $student->enroll($formation, EnrollmentSource::Purchase);

        $this->actingAs($student)
            ->post(route('checkout.pay', $formation), $this->proof())
            ->assertSessionHasErrors(['formation' => 'Vous avez déjà accès à cette formation : inutile de la payer une seconde fois.']);

        $this->assertSame(1, Purchase::count());
    }

    public function test_admin_validation_confirms_access_and_sends_the_receipt(): void
    {
        Event::fake([PaymentValidated::class]);
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 50000]);
        $this->actingAs($student)->post(route('checkout.pay', $formation), $this->proof());
        $purchase = Purchase::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $purchase))
            ->assertRedirect(route('admin.payments.index'));

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Validated, $purchase->status);
        $this->assertSame($admin->id, $purchase->verified_by);
        $this->assertNotNull($purchase->verified_at);
        $this->assertTrue($student->isEnrolledIn($formation));
        Event::assertDispatched(PaymentValidated::class);

        $this->actingAs($student)->get(route('student.purchases.receipt', $purchase))
            ->assertOk()
            ->assertSee('Reçu de paiement')
            ->assertSee($purchase->receipt_number)
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé, Togo')
            ->assertDontSee('RCCM');

        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase))->assertSessionHasErrors('purchase');
    }

    public function test_validation_email_is_sent(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->create(['status' => PurchaseStatus::Pending]);

        $this->actingAs($this->admin())->post(route('admin.payments.approve', $purchase));

        Mail::assertQueued(PaymentValidatedMail::class, fn (PaymentValidatedMail $mail): bool => $mail->hasTo($student->email));
    }

    public function test_rejection_removes_access_notifies_the_student_and_can_block_the_account(): void
    {
        Event::fake([FormationAccessRevoked::class]);
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 5500]);
        $this->actingAs($student)->post(route('checkout.pay', $formation), $this->proof());
        $purchase = Purchase::firstOrFail();
        $this->assertTrue($student->isEnrolledIn($formation));

        $this->actingAs($this->admin())
            ->post(route('admin.payments.reject', $purchase), ['reason' => 'Paiement non reçu sur notre compte.', 'block_user' => '1'])
            ->assertRedirect(route('admin.payments.index'));

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Rejected, $purchase->status);
        $this->assertSame('Paiement non reçu sur notre compte.', $purchase->rejection_reason);
        $this->assertFalse($student->isEnrolledIn($formation));
        $this->assertTrue($student->refresh()->isBlocked());
        Event::assertDispatched(FormationAccessRevoked::class);
        Mail::assertQueued(PaymentRejectedMail::class, fn (PaymentRejectedMail $mail): bool => $mail->hasTo($student->email));

        // Compte bloqué : déconnecté à la requête suivante et connexion refusée.
        $this->actingAs($student)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_rejection_requires_a_reason(): void
    {
        $purchase = Purchase::factory()->create(['status' => PurchaseStatus::Pending]);

        $this->actingAs($this->admin())
            ->post(route('admin.payments.reject', $purchase), ['reason' => ''])
            ->assertSessionHasErrorsIn('reject'.$purchase->id, 'reason');

        $this->assertSame(PurchaseStatus::Pending, $purchase->refresh()->status);
    }

    public function test_capture_and_receipt_are_private_to_their_owner_and_the_admin(): void
    {
        $owner = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 5500]);
        $this->actingAs($owner)->post(route('checkout.pay', $formation), $this->proof());
        $purchase = Purchase::firstOrFail();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase));
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('student.purchases.proof', $purchase))->assertForbidden();
        $this->actingAs($other)->get(route('student.purchases.receipt', $purchase))->assertForbidden();
        $this->actingAs($other)->get(route('student.purchases.show', $purchase))->assertForbidden();

        $this->actingAs($owner)->get(route('student.purchases.proof', $purchase))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', (string) $this->actingAs($admin)->get(route('student.purchases.proof', $purchase))->headers->get('Cache-Control'));

        auth()->logout();
        $this->get(route('student.purchases.proof', $purchase))->assertRedirect(route('login'));
    }

    public function test_receipt_is_only_available_for_a_validated_payment(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->create(['status' => PurchaseStatus::Provisional]);

        $this->actingAs($student)->get(route('student.purchases.receipt', $purchase))->assertForbidden();
    }

    public function test_payments_page_is_reserved_to_the_admin(): void
    {
        $purchase = Purchase::factory()->create(['status' => PurchaseStatus::Pending, 'payment_method' => 'bj-mtn', 'payment_method_name' => 'MTN', 'country' => 'bj']);

        $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.payments.approve', $purchase))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.payments.reject', $purchase), ['reason' => 'x'])->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Paiements à vérifier')
            ->assertSee($purchase->transaction_id)
            ->assertSee('À confirmer avec le représentant (Bénin)')
            ->assertSee(route('student.purchases.proof', $purchase), false);
    }

    public function test_student_sees_their_purchases(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->create(['status' => PurchaseStatus::Validated, 'verified_at' => now()]);
        Purchase::factory()->create(['transaction_id' => 'AUTRE-ELEVE']);

        $this->actingAs($student)->get(route('student.purchases.index'))
            ->assertOk()
            ->assertSee($purchase->formation->title)
            ->assertSee('Validé')
            ->assertSee(route('student.purchases.receipt', $purchase), false)
            ->assertDontSee('AUTRE-ELEVE');
    }

    public function test_proof_must_be_an_image(): void
    {
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 5500]);

        $this->actingAs($student)
            ->post(route('checkout.pay', $formation), $this->proof([
                'proof' => UploadedFile::fake()->create('releve.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('proof')
            ->assertSessionDoesntHaveErrors(['transaction_id', 'payer_phone', 'payer_name']);

        $this->assertSame(0, Purchase::count());
    }

    public function test_payment_emails_render(): void
    {
        $purchase = Purchase::factory()->create(['status' => PurchaseStatus::Validated, 'verified_at' => now(), 'rejection_reason' => 'Paiement non reçu.']);

        $this->assertStringContainsString($purchase->transaction_id, (new PaymentProofReceivedMail($purchase))->render());
        $this->assertStringContainsString('Voir et imprimer mon reçu', (new PaymentValidatedMail($purchase))->render());
        $this->assertStringContainsString('Paiement non reçu.', (new PaymentRejectedMail($purchase))->render());
        $this->assertStringContainsString('Vérifier le paiement', (new NewPaymentToVerifyMail($purchase))->render());
    }

    public function test_formation_above_threshold_announces_access_after_verification(): void
    {
        $formation = Formation::factory()->create(['price' => 300000]);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('Accès après vérification')
            ->assertDontSee('Votre formation s&#039;ouvre dès l&#039;envoi de votre preuve de paiement.', false);

        $this->actingAs(User::factory()->create())->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('Votre accès sera ouvert dès que votre paiement aura été vérifié et validé');
    }

    public function test_disabled_payment_method_is_neither_shown_nor_accepted(): void
    {
        config(['site.payments.methods.tg-mixx.enabled' => false]);
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 5500]);

        $this->actingAs($student)->get(route('checkout.show', $formation))->assertOk()->assertDontSee('Mixx by Yas');
        $this->actingAs($student)->post(route('checkout.pay', $formation), $this->proof())->assertSessionHasErrors('payment_method');
    }
}
