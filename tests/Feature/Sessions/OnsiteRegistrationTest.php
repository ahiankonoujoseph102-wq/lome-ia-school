<?php

namespace Tests\Feature\Sessions;

use App\Enums\PurchaseStatus;
use App\Enums\RegistrationStatus;
use App\Mail\LiveSessionMail;
use App\Models\LiveSessionRegistration;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use Illuminate\Support\Facades\Mail;

class OnsiteRegistrationTest extends SessionTestCase
{
    private function statusOf(User $user): ?RegistrationStatus
    {
        return LiveSessionRegistration::query()->where('user_id', $user->id)->first()?->status;
    }

    public function test_student_registers_for_a_free_onsite_session(): void
    {
        $session = $this->onsite(capacity: 10);
        $student = $this->student();

        $this->actingAs($student)->get(route('student.sessions.show', $session))->assertOk()->assertSee('M\'inscrire à la session', false)->assertSee('10 places restantes sur 10');
        $this->actingAs($student)->post(route('student.sessions.register', $session))->assertRedirect(route('student.sessions.show', $session));

        $this->assertSame(RegistrationStatus::Confirmed, $this->statusOf($student));
        $this->assertSame(9, $session->seatsLeft());
        $this->actingAs($student)->get(route('student.sessions.show', $session))->assertSee('Vous êtes inscrit(e)');

        // Deuxième clic : aucune place en plus.
        $this->actingAs($student)->post(route('student.sessions.register', $session));
        $this->assertSame(9, $session->seatsLeft());
    }

    public function test_only_buyers_can_register(): void
    {
        $session = $this->onsite();

        $this->actingAs(User::factory()->create())->post(route('student.sessions.register', $session))->assertForbidden();
        $this->actingAs($this->student())->post(route('student.sessions.register', $this->online()))->assertForbidden();
        $this->assertSame(0, LiveSessionRegistration::query()->count());
    }

    public function test_full_session_opens_a_waitlist_and_a_freed_seat_goes_to_the_first_in_line(): void
    {
        $session = $this->onsite(capacity: 1);
        [$first, $second, $third] = [$this->student(), $this->student(['name' => 'Deuxième']), $this->student()];

        $this->actingAs($first)->post(route('student.sessions.register', $session));
        $this->actingAs($second)->post(route('student.sessions.register', $session));
        $this->travel(1)->minutes();
        $this->actingAs($third)->post(route('student.sessions.register', $session));

        $this->assertSame(RegistrationStatus::Confirmed, $this->statusOf($first));
        $this->assertSame(RegistrationStatus::Waitlisted, $this->statusOf($second));
        $this->assertSame(RegistrationStatus::Waitlisted, $this->statusOf($third));
        $this->actingAs($third)->get(route('student.sessions.show', $session))->assertSee('2e sur la liste d\'attente', false);
        $this->get(route('formations.show', $this->formation))->assertSee('Complet · liste d\'attente ouverte');

        // Une place se libère : la première personne en attente est inscrite et prévenue.
        $this->actingAs($first)->delete(route('student.sessions.cancel', $session))->assertRedirect();

        $this->assertSame(RegistrationStatus::Cancelled, $this->statusOf($first));
        $this->assertSame(RegistrationStatus::Confirmed, $this->statusOf($second));
        $this->assertSame(RegistrationStatus::Waitlisted, $this->statusOf($third));
        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($second->email) && $mail->kind === 'seat_available');
        Mail::assertNotQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($third->email));
    }

    public function test_cancellation_is_allowed_until_the_deadline_only(): void
    {
        config(['live_sessions.cancel_deadline_hours' => 24]);
        $session = $this->onsite(attributes: ['starts_at' => now()->addHours(30)]);
        $early = $this->student();
        $late = $this->student();
        $this->actingAs($early)->post(route('student.sessions.register', $session));
        $this->actingAs($late)->post(route('student.sessions.register', $session));

        $this->actingAs($early)->delete(route('student.sessions.cancel', $session))->assertSessionHasNoErrors();
        $this->assertSame(RegistrationStatus::Cancelled, $this->statusOf($early));

        $this->travel(7)->hours();
        $this->actingAs($late)->delete(route('student.sessions.cancel', $session))->assertSessionHasErrors('session');
        $this->assertSame(RegistrationStatus::Confirmed, $this->statusOf($late));
        $this->actingAs($late)->get(route('student.sessions.show', $session))->assertSee('Le délai d\'annulation est dépassé', false);
    }

    public function test_cancellation_deadline_is_configurable(): void
    {
        config(['live_sessions.cancel_deadline_hours' => 2]);
        $session = $this->onsite(attributes: ['starts_at' => now()->addHours(3)]);
        $student = $this->student();
        $this->actingAs($student)->post(route('student.sessions.register', $session));

        $this->actingAs($student)->delete(route('student.sessions.cancel', $session))->assertSessionHasNoErrors();
        $this->assertSame(RegistrationStatus::Cancelled, $this->statusOf($student));
    }

    public function test_access_removed_cancels_the_registration_and_frees_the_seat(): void
    {
        $session = $this->onsite(capacity: 1);
        $purchase = Purchase::factory()->for($this->formation)->status(PurchaseStatus::Provisional)->create();
        $manager = app(PurchaseManager::class);
        $manager->grantAccess($purchase);
        $waiting = $this->student();

        $this->actingAs($purchase->user)->post(route('student.sessions.register', $session));
        $this->actingAs($waiting)->post(route('student.sessions.register', $session));

        $manager->reject($purchase, $this->admin(), 'Paiement non reçu.');

        $this->assertSame(RegistrationStatus::Cancelled, $this->statusOf($purchase->user));
        $this->assertSame(RegistrationStatus::Confirmed, $this->statusOf($waiting));
    }

    public function test_blocked_account_loses_its_seat(): void
    {
        $session = $this->onsite();
        $student = $this->student();
        $this->actingAs($student)->post(route('student.sessions.register', $session));

        app(PurchaseManager::class)->block($student);

        $this->assertSame(RegistrationStatus::Cancelled, $this->statusOf($student));
    }
}
