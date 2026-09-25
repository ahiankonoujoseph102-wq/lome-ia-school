<?php

namespace Tests\Feature\Consultations;

use App\Enums\ConsultationAttendance;
use App\Enums\ConsultationStatus;
use App\Enums\UserRole;
use App\Mail\ConsultationMail;
use App\Models\ConsultationBlock;
use App\Models\ConsultationHour;
use App\Models\ConsultationSettings;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AdminConsultationsTest extends ConsultationTestCase
{
    public function test_admin_pages_are_forbidden_to_students_and_moderators(): void
    {
        [$consultation] = $this->booked('2026-10-06 10:15');
        $hour = ConsultationHour::query()->firstOrFail();
        $block = ConsultationBlock::query()->create(['starts_at' => '2026-10-20 00:00', 'ends_at' => '2026-10-21 00:00']);

        foreach ([User::factory()->create(), User::factory()->create(['role' => UserRole::Moderator])] as $user) {
            $this->actingAs($user)->get(route('admin.consultations.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.consultations.availability'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.consultations.cancel', $consultation), ['message' => 'Non'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.consultations.attendance', $consultation), ['attendance' => 'present'])->assertForbidden();
            $this->actingAs($user)->put(route('admin.consultations.follow-up', $consultation), ['admin_notes' => 'x'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.consultations.recommend', $consultation))->assertForbidden();
            $this->actingAs($user)->put(route('admin.consultations.settings'), ['meet_url' => 'https://meet.google.com/zzz-zzzz-zzz'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.consultations.hours.destroy', $hour))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.consultations.blocks.destroy', $block))->assertForbidden();
        }

        $this->assertSame(self::MEET, ConsultationSettings::current()->meet_url);
        $this->assertSame(ConsultationStatus::Booked, $consultation->refresh()->status);
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.consultations.index'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_the_visitor_details_and_today_counter(): void
    {
        $this->booked('2026-10-05 10:15', ['name' => 'Kafui Mensah', 'email' => 'kafui@example.com', 'topic' => 'Créer mon site vitrine']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.consultations.index'))
            ->assertOk()
            ->assertSee('Kafui Mensah')
            ->assertSee('kafui@example.com')
            ->assertSee('Créer mon site vitrine')
            ->assertSee('https://wa.me/22890123411', false);

        $this->actingAs($admin)->get(route('admin.index'))->assertSee('Consultations du jour')->assertSee('aria-label="Consultations du jour : 1"', false);
    }

    public function test_admin_cancels_with_a_message_to_the_visitor(): void
    {
        [$consultation] = $this->booked('2026-10-06 10:15');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.consultations.cancel', $consultation), ['message' => ''])->assertSessionHasErrors('message', null, 'cancel'.$consultation->id);
        $this->actingAs($admin)->post(route('admin.consultations.cancel', $consultation), ['message' => 'Un empêchement, désolé.'])->assertRedirect();

        $consultation->refresh();
        $this->assertSame(ConsultationStatus::Cancelled, $consultation->status);
        $this->assertSame('admin', $consultation->cancelled_by);
        $this->assertContains('10:15', $this->slotsOn('2026-10-06'));
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'cancelled' && str_contains($mail->render(), 'Un empêchement, désolé.'));
    }

    public function test_attendance_notes_and_recommendation(): void
    {
        [$consultation] = $this->booked('2026-10-06 10:15');
        $formation = Formation::factory()->create(['title' => 'Création d\'affiches pro avec l\'IA', 'slug' => 'creation-affiches-pro-ia']);
        $admin = $this->admin();
        $this->travelTo('2026-10-06 11:00');

        $this->actingAs($admin)->post(route('admin.consultations.attendance', $consultation), ['attendance' => 'absent'])->assertRedirect();
        $this->assertSame(ConsultationAttendance::Absent, $consultation->refresh()->attendance);
        $this->actingAs($admin)->post(route('admin.consultations.attendance', $consultation), ['attendance' => 'present']);
        $this->assertSame(ConsultationAttendance::Present, $consultation->refresh()->attendance);

        // Pas de formation choisie : rien n'est envoyé.
        $this->actingAs($admin)->post(route('admin.consultations.recommend', $consultation))->assertSessionHasErrors('recommendation'.$consultation->id);
        Mail::assertNotQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'recommendation');

        $this->actingAs($admin)->put(route('admin.consultations.follow-up', $consultation), ['admin_notes' => 'Débutante, commerce de pagnes.', 'recommended_formation_id' => $formation->id])->assertRedirect();
        $consultation->refresh();
        $this->assertSame('Débutante, commerce de pagnes.', $consultation->admin_notes);
        $this->assertSame($formation->id, $consultation->recommended_formation_id);

        $this->actingAs($admin)->post(route('admin.consultations.recommend', $consultation))->assertRedirect();
        $this->assertNotNull($consultation->refresh()->recommendation_sent_at);
        Mail::assertQueued(ConsultationMail::class, function (ConsultationMail $mail) use ($formation): bool {
            $html = $mail->render();

            return $mail->kind === 'recommendation' && str_contains($html, route('formations.show', $formation)) && ! str_contains($html, 'Débutante');
        });

        // Les notes privées ne sont jamais montrées au visiteur.
        $this->get($consultation->manageUrl())->assertOk()->assertDontSee('Débutante');
    }

    public function test_admin_updates_settings_and_the_meet_link(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.consultations.availability'))->assertOk()->assertSee(self::MEET)->assertSee('Lundi')->assertSee('8 h 00 – 20 h 00');

        $settings = ['duration_minutes' => 30, 'buffer_minutes' => 15, 'min_notice_hours' => 3, 'max_days_ahead' => 30, 'max_per_day' => '', 'manage_deadline_hours' => 3];

        $this->actingAs($admin)->put(route('admin.consultations.settings'), [...$settings, 'meet_url' => 'https://zoom.us/j/123'])->assertSessionHasErrors('meet_url', null, 'settings');
        $this->assertSame(self::MEET, ConsultationSettings::current()->meet_url);

        $this->actingAs($admin)->put(route('admin.consultations.settings'), [...$settings, 'meet_url' => 'meet.google.com/XYZ-ABCD-EFG', 'buffer_minutes' => 30, 'max_per_day' => 4])->assertSessionHasNoErrors();
        $current = ConsultationSettings::current();
        $this->assertSame('https://meet.google.com/xyz-abcd-efg', $current->meet_url);
        $this->assertSame(30, $current->buffer_minutes);
        $this->assertSame(4, $current->max_per_day);
        $this->assertSame(['08:00', '09:00'], array_slice($this->slotsOn('2026-10-06'), 0, 2));
    }

    public function test_admin_manages_weekly_hours_and_blocked_periods(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.consultations.hours.store'), ['weekday' => 7, 'starts_at' => '15:00', 'ends_at' => '14:00'])->assertSessionHasErrors('ends_at', null, 'hours');
        $this->actingAs($admin)->post(route('admin.consultations.hours.store'), ['weekday' => 1, 'starts_at' => '19:00', 'ends_at' => '21:00'])->assertSessionHasErrors('starts_at', null, 'hours');
        $this->actingAs($admin)->post(route('admin.consultations.hours.store'), ['weekday' => 7, 'starts_at' => '15:00', 'ends_at' => '16:00'])->assertSessionHasNoErrors();
        $this->assertSame(['15:00'], $this->slotsOn('2026-10-11'));

        $saturday = ConsultationHour::query()->where('weekday', 6)->firstOrFail();
        $this->actingAs($admin)->delete(route('admin.consultations.hours.destroy', $saturday))->assertRedirect();
        $this->assertSame([], $this->slotsOn('2026-10-10'));

        [$consultation] = $this->booked('2026-10-08 10:15');
        $response = $this->actingAs($admin)->post(route('admin.consultations.blocks.store'), ['start_date' => '2026-10-08', 'reason' => 'Congés'])->assertSessionHasNoErrors();
        $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '1 rendez-vous déjà réservé'));
        $this->assertSame([], $this->slotsOn('2026-10-08'));
        $this->assertSame(ConsultationStatus::Booked, $consultation->refresh()->status);

        $this->actingAs($admin)->post(route('admin.consultations.blocks.store'), ['start_date' => '2026-10-09', 'start_time' => '14:00', 'end_time' => '16:00'])->assertSessionHasNoErrors();
        $friday = $this->slotsOn('2026-10-09');
        $this->assertContains('13:15', $friday);
        $this->assertNotContains('14:00', $friday);
        $this->assertContains('16:15', $friday);

        $block = ConsultationBlock::query()->where('reason', 'Congés')->firstOrFail();
        $this->actingAs($admin)->delete(route('admin.consultations.blocks.destroy', $block))->assertRedirect();
        $this->assertContains('09:30', $this->slotsOn('2026-10-08'));
    }
}
