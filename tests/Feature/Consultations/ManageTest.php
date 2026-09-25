<?php

namespace Tests\Feature\Consultations;

use App\Enums\ConsultationStatus;
use App\Mail\ConsultationAdminMail;
use App\Mail\ConsultationMail;
use App\Models\Consultation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ManageTest extends ConsultationTestCase
{
    public function test_manage_link_is_secret_and_unique(): void
    {
        [, $token] = $this->booked('2026-10-06 10:15');

        $this->get(route('consultation.manage', $token))->assertOk()->assertSee(self::MEET)->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('consultation.manage', Str::random(48)))->assertNotFound();
        $this->get(route('consultation.manage', 'court'))->assertNotFound();
        $this->put(route('consultation.move', Str::random(48)), ['starts_at' => '2026-10-07 10:15'])->assertNotFound();
        $this->delete(route('consultation.cancel', Str::random(48)))->assertNotFound();

        // Le jeton n'est pas enregistré en clair.
        $this->assertStringNotContainsString($token, (string) Consultation::query()->toBase()->value('manage_token'));
    }

    public function test_visitor_moves_the_booking_and_the_old_slot_is_freed(): void
    {
        [$consultation, $token] = $this->booked('2026-10-06 10:15');
        $consultation->forceFill(['day_reminder_sent_at' => now()])->save();

        $this->put(route('consultation.move', $token), ['starts_at' => '2026-10-07 14:00'])
            ->assertRedirect(route('consultation.manage', $token))
            ->assertSessionHasNoErrors();

        $consultation->refresh();
        $this->assertSame('2026-10-07 14:00', $consultation->starts_at->format('Y-m-d H:i'));
        $this->assertNull($consultation->day_reminder_sent_at);
        $this->assertContains('10:15', $this->slotsOn('2026-10-06'));
        $this->assertNotContains('14:00', $this->slotsOn('2026-10-07'));
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'moved');
        Mail::assertQueued(ConsultationAdminMail::class, fn (ConsultationAdminMail $mail): bool => $mail->kind === 'moved');

        // Un créneau déjà pris est refusé.
        $this->booked('2026-10-08 09:30');
        $this->put(route('consultation.move', $token), ['starts_at' => '2026-10-08 09:30'])->assertSessionHasErrors('starts_at');
    }

    public function test_visitor_cancels_and_the_slot_is_available_again(): void
    {
        [$consultation, $token] = $this->booked('2026-10-06 10:15');

        $this->delete(route('consultation.cancel', $token))->assertRedirect(route('consultation.manage', $token));

        $consultation->refresh();
        $this->assertSame(ConsultationStatus::Cancelled, $consultation->status);
        $this->assertSame('visitor', $consultation->cancelled_by);
        $this->assertNull($consultation->slot_key);
        $this->assertContains('10:15', $this->slotsOn('2026-10-06'));
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'cancelled');
        Mail::assertQueued(ConsultationAdminMail::class, fn (ConsultationAdminMail $mail): bool => $mail->kind === 'cancelled');

        // Plus de lien Meet sur un rendez-vous annulé ; un autre visiteur peut prendre le créneau.
        $this->get(route('consultation.manage', $token))->assertOk()->assertSee('Annulée')->assertDontSee(self::MEET);
        $this->book('2026-10-06 10:15')->assertSessionHasNoErrors();
    }

    public function test_changes_are_refused_after_the_deadline(): void
    {
        [$consultation, $token] = $this->booked('2026-10-06 10:15');

        // 2 h avant : le délai de 3 h est dépassé.
        $this->travelTo($consultation->starts_at->copy()->subHours(2));

        $this->get(route('consultation.manage', $token))->assertOk()->assertSee('ne peut plus être déplacé ni annulé en ligne')->assertDontSee('Confirmer l&#039;annulation', false);
        $this->put(route('consultation.move', $token), ['starts_at' => '2026-10-07 10:15'])->assertSessionHasErrors('starts_at');
        $this->delete(route('consultation.cancel', $token))->assertSessionHasErrors('consultation');

        $this->assertSame(ConsultationStatus::Booked, $consultation->refresh()->status);
        $this->assertSame('2026-10-06 10:15', $consultation->starts_at->format('Y-m-d H:i'));
    }

    public function test_calendar_file(): void
    {
        [, $token] = $this->booked('2026-10-06 10:15');

        $ics = (string) $this->get(route('consultation.calendar', $token))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('DTSTART:20261006T101500Z', $ics);
        $this->assertStringContainsString('DTEND:20261006T104500Z', $ics);
        $this->assertStringContainsString('URL:'.self::MEET, $ics);
    }

    public function test_reminders_the_day_before_and_one_hour_before(): void
    {
        [$early] = $this->booked('2026-10-07 10:15', ['email' => 'tot@example.com']);

        // La veille : un seul rappel, même si la tâche tourne plusieurs fois.
        $this->travelTo('2026-10-06 12:00');
        $this->artisan('app:process-consultations')->assertSuccessful();
        $this->artisan('app:process-consultations')->assertSuccessful();
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'reminder_day' && $mail->hasTo('tot@example.com'));
        Mail::assertQueuedCount(3); // confirmation + e-mail admin + un seul rappel

        // 1 h avant.
        $this->travelTo('2026-10-07 09:20');
        $this->artisan('app:process-consultations')->assertSuccessful();
        $this->artisan('app:process-consultations')->assertSuccessful();
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'reminder_hour' && str_contains($mail->render(), self::MEET));
        Mail::assertQueuedCount(4);
        $this->assertNotNull($early->refresh()->hour_reminder_sent_at);
    }

    public function test_no_day_reminder_for_a_late_booking_and_none_for_a_cancelled_one(): void
    {
        $this->travelTo('2026-10-06 06:00');
        $this->booked('2026-10-06 12:30', ['email' => 'tard@example.com']);
        [, $token] = $this->booked('2026-10-06 14:45', ['email' => 'annule@example.com']);
        $this->delete(route('consultation.cancel', $token));

        $this->travelTo('2026-10-06 08:00');
        $this->artisan('app:process-consultations')->assertSuccessful();

        Mail::assertNotQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'reminder_day');

        $this->travelTo('2026-10-06 11:45');
        $this->artisan('app:process-consultations')->assertSuccessful();
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'reminder_hour' && $mail->hasTo('tard@example.com'));
        Mail::assertNotQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'reminder_hour' && $mail->hasTo('annule@example.com'));
    }

    public function test_old_consultations_are_deleted_after_the_retention_period(): void
    {
        $this->booked('2026-10-06 10:15');

        $this->travelTo('2027-10-05 12:00');
        $this->artisan('app:process-consultations')->assertSuccessful();
        $this->assertSame(1, Consultation::query()->count());

        $this->travelTo('2027-10-07 12:00');
        $this->artisan('app:process-consultations')->assertSuccessful();
        $this->assertSame(0, Consultation::query()->count());
    }
}
