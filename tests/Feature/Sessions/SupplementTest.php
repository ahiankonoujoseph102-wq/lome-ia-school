<?php

namespace Tests\Feature\Sessions;

use App\Enums\PurchaseStatus;
use App\Enums\RegistrationStatus;
use App\Mail\LiveSessionMail;
use App\Mail\NewPaymentToVerifyMail;
use App\Models\LiveSession;
use App\Models\LiveSessionRegistration;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

class SupplementTest extends SessionTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function proof(string $transaction = 'MP261010.1001.A12345'): array
    {
        return [
            'payment_method' => 'tg-mixx',
            'payer_phone' => '90 12 34 56',
            'payer_name' => 'Afi Dossou',
            'transaction_id' => $transaction,
            'proof' => UploadedFile::fake()->image('capture.png', 600, 1200),
        ];
    }

    private function registration(User $user): LiveSessionRegistration
    {
        return LiveSessionRegistration::query()->where('user_id', $user->id)->firstOrFail();
    }

    private function reserve(LiveSession $session, User $student): void
    {
        $this->actingAs($student)->post(route('student.sessions.register', $session))->assertRedirect(route('student.sessions.payment', $session));
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($student)->status);
    }

    public function test_small_supplement_confirms_the_seat_at_once_and_appears_in_purchases(): void
    {
        $session = $this->onsite(capacity: 5, supplement: 5000);
        $student = $this->student();
        $this->reserve($session, $student);

        $this->actingAs($student)->get(route('student.sessions.payment', $session))->assertOk()->assertSee(Money::fcfa(5000))->assertSee('Mixx by Yas');
        $this->actingAs($student)->post(route('student.sessions.pay', $session), $this->proof())->assertRedirect();

        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->assertSame($session->id, $purchase->live_session_id);
        $this->assertSame(5000, $purchase->amount);
        $this->assertSame(RegistrationStatus::Confirmed, $this->registration($student)->status);
        Mail::assertQueued(NewPaymentToVerifyMail::class);

        $this->actingAs($student)->get(route('student.purchases.index'))->assertSee('Supplément · Session en présentiel du');
        $this->actingAs($this->admin())->get(route('admin.payments.index'))->assertSee('Supplément d\'une session en présentiel', false);

        app(PurchaseManager::class)->validate($purchase, $this->admin());
        $this->actingAs($student)->get(route('student.purchases.receipt', $purchase))->assertOk()->assertSee('Session')->assertSee(Money::fcfa(5000));
    }

    public function test_large_supplement_keeps_the_seat_reserved_during_verification(): void
    {
        $session = $this->onsite(capacity: 1, supplement: 60000);
        $student = $this->student();
        $this->reserve($session, $student);

        $this->actingAs($student)->post(route('student.sessions.pay', $session), $this->proof())->assertRedirect();

        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertTrue($this->registration($student)->paymentUnderReview());
        $this->assertTrue($session->isFull());

        // Même la tâche du cron ne libère pas une place dont le paiement est en vérification.
        $this->travel(3)->days();
        $this->artisan('app:process-live-sessions')->assertSuccessful();
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($student)->status);

        app(PurchaseManager::class)->validate($purchase, $this->admin());
        $this->assertSame(RegistrationStatus::Confirmed, $this->registration($student)->status);
    }

    public function test_rejected_supplement_frees_the_seat_for_the_waitlist(): void
    {
        $session = $this->onsite(capacity: 1, supplement: 60000);
        $payer = $this->student();
        $waiting = $this->student();
        $this->reserve($session, $payer);
        $this->actingAs($waiting)->post(route('student.sessions.register', $session));
        $this->assertSame(RegistrationStatus::Waitlisted, $this->registration($waiting)->status);

        $this->actingAs($payer)->post(route('student.sessions.pay', $session), $this->proof());
        app(PurchaseManager::class)->reject(Purchase::query()->firstOrFail(), $this->admin(), 'Paiement non reçu sur notre compte.');

        $this->assertSame(RegistrationStatus::Cancelled, $this->registration($payer)->status);
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($waiting)->status);
        $this->assertTrue($payer->isEnrolledIn($this->formation), 'L\'accès à la formation n\'est pas touché.');
        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($waiting->email) && $mail->kind === 'seat_available');
    }

    public function test_unpaid_reservation_expires_and_goes_to_the_waitlist(): void
    {
        config(['live_sessions.payment_hold_hours' => 24]);
        $session = $this->onsite(capacity: 1, supplement: 5000, attributes: ['starts_at' => now()->addDays(10)]);
        $slow = $this->student();
        $waiting = $this->student();
        $this->reserve($session, $slow);
        $this->actingAs($waiting)->post(route('student.sessions.register', $session));

        $this->travel(25)->hours();
        $this->artisan('app:process-live-sessions')->assertSuccessful();

        $this->assertSame('payment_expired', $this->registration($slow)->cancel_reason);
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($waiting)->status);
        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($slow->email) && $mail->kind === 'reservation_expired');
    }

    public function test_supplement_cannot_be_paid_without_a_reserved_seat_or_twice(): void
    {
        $session = $this->onsite(capacity: 5, supplement: 5000);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.sessions.pay', $session), $this->proof())->assertSessionHasErrors('formation');

        $this->reserve($session, $student);
        $this->actingAs($student)->post(route('student.sessions.pay', $session), $this->proof());
        $this->actingAs($student)->post(route('student.sessions.pay', $session), $this->proof('AUTRE.123'))->assertSessionHasErrors('formation');

        $this->assertSame(1, Purchase::query()->count());
    }
}
