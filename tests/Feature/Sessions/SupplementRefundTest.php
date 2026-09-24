<?php

namespace Tests\Feature\Sessions;

use App\Enums\PurchaseStatus;
use App\Enums\RegistrationStatus;
use App\Models\LiveSession;
use App\Models\LiveSessionRegistration;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use Illuminate\Http\UploadedFile;

/**
 * Remboursement du supplément : annulation par l'élève dans le délai ou annulation par l'école
 * (remboursé), absence sans annulation (pas remboursé).
 */
class SupplementRefundTest extends SessionTestCase
{
    private function registration(User $user): LiveSessionRegistration
    {
        return LiveSessionRegistration::query()->where('user_id', $user->id)->firstOrFail();
    }

    private function pay(LiveSession $session, User $student, string $transaction = 'MP261010.1001.A12345', int $height = 1200): Purchase
    {
        $this->actingAs($student)->post(route('student.sessions.register', $session))->assertRedirect(route('student.sessions.payment', $session));
        $this->actingAs($student)->post(route('student.sessions.pay', $session), [
            'payment_method' => 'tg-mixx',
            'payer_phone' => '90 12 34 56',
            'payer_name' => 'Afi Dossou',
            'transaction_id' => $transaction,
            'proof' => UploadedFile::fake()->image('capture.png', 600, $height),
        ])->assertRedirect();

        return Purchase::query()->where('user_id', $student->id)->latest('id')->firstOrFail();
    }

    public function test_student_cancelling_in_time_gets_the_supplement_listed_for_refund(): void
    {
        $session = $this->onsite(capacity: 1, supplement: 5000);
        $student = $this->student();
        $waiting = $this->student();
        $purchase = $this->pay($session, $student);
        $this->assertSame(RegistrationStatus::Confirmed, $this->registration($student)->status);
        $this->actingAs($waiting)->post(route('student.sessions.register', $session));

        $this->actingAs($student)->get(route('student.sessions.show', $session))->assertSee('Le supplément payé vous est alors remboursé par Mobile Money.');
        $this->actingAs($student)->delete(route('student.sessions.cancel', $session))
            ->assertRedirect(route('student.sessions.show', $session))
            ->assertSessionHas('status', 'Votre inscription est annulée. Le supplément payé vous sera remboursé par Mobile Money.');

        $this->assertNotNull($purchase->refresh()->refund_due_at);
        $this->assertSame(RegistrationStatus::Cancelled, $this->registration($student)->status);
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($waiting)->status);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.sessions.index'))->assertSee('1 supplément(s) à rembourser');
        $this->actingAs($admin)->get(route('admin.sessions.show', $session))
            ->assertSee('Suppléments à rembourser')
            ->assertSee('Inscription annulée par l\'élève');

        $this->actingAs($admin)->post(route('admin.sessions.refund', [$session, $purchase]))->assertRedirect();
        $this->assertSame(PurchaseStatus::Refunded, $purchase->refresh()->status);
        $this->assertTrue($student->isEnrolledIn($this->formation));
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($waiting)->status);
        $this->actingAs($admin)->get(route('admin.sessions.show', $session))->assertDontSee('Suppléments à rembourser');
    }

    public function test_school_cancellation_and_student_cancellation_share_the_same_list(): void
    {
        $session = $this->onsite(capacity: 5, supplement: 5000);
        $leaving = $this->student(['name' => 'Élève qui annule']);
        $staying = $this->student(['name' => 'Élève inscrit']);
        $this->pay($session, $leaving);
        $this->pay($session, $staying, 'MP261010.1001.B67890', 1300);
        $this->actingAs($leaving)->delete(route('student.sessions.cancel', $session));

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.sessions.show', $session))
            ->assertSee('Élève qui annule')
            ->assertDontSee('Session annulée par l\'école');
        $this->assertSame(1, Purchase::query()->refundDue()->count());

        $this->actingAs($admin)->post(route('admin.sessions.cancel', $session), ['reason' => 'Salle indisponible'])->assertRedirect();

        $this->assertSame(2, Purchase::query()->refundDue()->count());
        $this->actingAs($admin)->get(route('admin.sessions.show', $session))
            ->assertSee('Élève qui annule')
            ->assertSee('Élève inscrit')
            ->assertSee('Session annulée par l\'école');
    }

    public function test_absent_student_who_did_not_cancel_is_not_refunded(): void
    {
        $session = $this->onsite(capacity: 5, supplement: 5000);
        $student = $this->student();
        $purchase = $this->pay($session, $student);

        $this->travelTo($session->ends_at->copy()->addDay());

        $this->assertNull($purchase->refresh()->refund_due_at);
        $this->assertSame(0, Purchase::query()->refundDue()->count());
        $this->actingAs($this->admin())->get(route('admin.sessions.show', $session))->assertDontSee('Suppléments à rembourser');
    }

    public function test_cancelling_after_the_deadline_is_refused_and_not_refunded(): void
    {
        $session = $this->onsite(capacity: 5, supplement: 5000);
        $student = $this->student();
        $purchase = $this->pay($session, $student);

        $this->travelTo($session->starts_at->copy()->subHours(2));

        $this->actingAs($student)->delete(route('student.sessions.cancel', $session))->assertSessionHasErrors('session');
        $this->assertSame(RegistrationStatus::Confirmed, $this->registration($student)->status);
        $this->assertNull($purchase->refresh()->refund_due_at);
        $this->assertSame(0, Purchase::query()->refundDue()->count());
    }

    public function test_cancelling_an_unpaid_reservation_lists_nothing_to_refund(): void
    {
        $session = $this->onsite(capacity: 5, supplement: 5000);
        $student = $this->student();
        $this->actingAs($student)->post(route('student.sessions.register', $session));

        $this->actingAs($student)->delete(route('student.sessions.cancel', $session))
            ->assertSessionHas('status', 'Votre inscription est annulée.');

        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_old_supplement_never_confirms_or_frees_a_new_registration(): void
    {
        $session = $this->onsite(capacity: 5, supplement: 5000);
        $student = $this->student();
        $old = $this->pay($session, $student);
        $this->actingAs($student)->delete(route('student.sessions.cancel', $session));

        // L'élève se réinscrit : nouvelle place réservée, nouveau supplément à payer.
        $this->actingAs($student)->post(route('student.sessions.register', $session))->assertRedirect(route('student.sessions.payment', $session));
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($student)->status);

        $admin = $this->admin();
        app(PurchaseManager::class)->validate($old, $admin);
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($student)->status);

        $this->actingAs($admin)->post(route('admin.sessions.refund', [$session, $old]))->assertRedirect();
        $this->assertSame(PurchaseStatus::Refunded, $old->refresh()->status);
        $this->assertSame(RegistrationStatus::Reserved, $this->registration($student)->status);
    }
}
