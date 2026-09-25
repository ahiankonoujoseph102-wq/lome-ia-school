<?php

namespace Tests\Feature\Consultations;

use App\Enums\UserRole;
use App\Mail\ConsultationMail;
use App\Models\ConsultationSettings;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Lien Google Meet fixe pas encore renseigné : alerte dans l'admin, puis lien présent partout
 * (page de gestion, rappels, agenda) dès qu'il est saisi, y compris pour les réservations faites avant.
 */
class MeetLinkTest extends ConsultationTestCase
{
    private const ALERT = 'Lien Google Meet de la consultation non renseigné';

    protected function setUp(): void
    {
        parent::setUp();

        ConsultationSettings::current()->update(['meet_url' => null]);
    }

    public function test_admin_sees_an_alert_on_admin_pages_while_the_link_is_missing(): void
    {
        $admin = $this->admin();

        foreach (['admin.index', 'admin.consultations.index', 'admin.payments.index', 'admin.sessions.index'] as $route) {
            $this->actingAs($admin)->get(route($route))
                ->assertOk()
                ->assertSee(self::ALERT)
                ->assertSee(route('admin.consultations.availability').'#meet_url', false);
        }

        // Sur l'écran « Disponibilités », l'alerte reste mais sans lien vers lui-même.
        $this->actingAs($admin)->get(route('admin.consultations.availability'))
            ->assertOk()
            ->assertSee(self::ALERT)
            ->assertDontSee(route('admin.consultations.availability').'#meet_url', false);

        // Jamais sur les pages publiques ni dans l'espace élève.
        $this->actingAs($admin)->get(route('home'))->assertOk()->assertDontSee(self::ALERT);
        $this->actingAs($admin)->get(route('consultation'))->assertOk()->assertDontSee(self::ALERT);
    }

    public function test_alert_is_hidden_for_moderators_and_once_the_link_is_set(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $this->actingAs($moderator)->get(route('admin.sessions.index'))->assertOk()->assertDontSee(self::ALERT);

        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.consultations.settings'), [
            'meet_url' => self::MEET,
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
            'min_notice_hours' => 3,
            'max_days_ahead' => 30,
            'max_per_day' => '',
            'manage_deadline_hours' => 3,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertDontSee(self::ALERT);
        $this->actingAs($admin)->get(route('admin.consultations.availability'))->assertOk()->assertDontSee(self::ALERT);
    }

    public function test_link_added_later_reaches_earlier_bookings(): void
    {
        [$consultation, $token] = $this->booked('2026-10-07 10:15', ['email' => 'avant@example.com']);

        // Sans lien : page de gestion, confirmation et agenda l'annoncent pour plus tard.
        $this->get(route('consultation.manage', $token))
            ->assertOk()
            ->assertSee('Le lien vous sera envoyé par e-mail avant le rendez-vous.')
            ->assertDontSee('meet.google.com');
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'confirmed'
            && str_contains($mail->render(), 'Le lien de l\'appel vidéo vous sera envoyé par e-mail avant le rendez-vous.'));
        $this->assertStringNotContainsString('meet.google.com', $this->get(route('consultation.calendar', $token))->getContent() ?: '');

        // L'admin saisit le lien après la réservation.
        ConsultationSettings::current()->update(['meet_url' => self::MEET]);

        $this->get(route('consultation.manage', $token))
            ->assertOk()
            ->assertSee(self::MEET)
            ->assertSee('Rejoindre l\'appel vidéo', false)
            ->assertDontSee('Le lien vous sera envoyé par e-mail avant le rendez-vous.');
        $this->assertStringContainsString('URL:'.self::MEET, $this->get(route('consultation.calendar', $token))->getContent() ?: '');

        // Rappel de la veille et rappel 1 h avant : le lien y figure.
        $this->travelTo('2026-10-06 12:00');
        $this->artisan('app:process-consultations')->assertSuccessful();
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'reminder_day'
            && $mail->hasTo('avant@example.com')
            && str_contains($mail->render(), self::MEET)
            && ! str_contains($mail->render(), 'vous sera envoyé'));

        $this->travelTo('2026-10-07 09:20');
        $this->artisan('app:process-consultations')->assertSuccessful();
        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'reminder_hour'
            && $mail->hasTo('avant@example.com')
            && str_contains($mail->render(), self::MEET));

        $this->assertNotNull($consultation->refresh()->hour_reminder_sent_at);
    }
}
