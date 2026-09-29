<?php

namespace Tests\Feature\Consultations;

use App\Consultations\ConsultationCalendar;
use App\Mail\ConsultationMail;
use App\Support\GoogleCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * « Ajouter à Google Agenda » : lien qui ouvre Google Agenda avec le rendez-vous prérempli,
 * à côté du fichier .ics (que beaucoup d'élèves ne savent pas ouvrir sur téléphone).
 */
class GoogleCalendarTest extends ConsultationTestCase
{
    /**
     * @return array<string, string>
     */
    private static function params(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return array_map('strval', $query);
    }

    public function test_the_link_prefills_title_dates_duration_and_lome_time_zone(): void
    {
        $start = CarbonImmutable::parse('2026-10-06 10:15', 'Africa/Lome');
        $url = GoogleCalendar::url('Consultation gratuite · Lomé IA School', $start, $start->addMinutes(30), "Ligne 1\nLigne 2", 'Google Meet');

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);

        $query = self::params($url);
        $this->assertSame('TEMPLATE', $query['action']);
        $this->assertSame('Consultation gratuite · Lomé IA School', $query['text']);
        $this->assertSame('20261006T101500/20261006T104500', $query['dates']);
        $this->assertSame('Africa/Lome', $query['ctz']);
        $this->assertSame("Ligne 1\nLigne 2", $query['details']);
        $this->assertSame('Google Meet', $query['location']);
    }

    public function test_dates_given_in_another_time_zone_are_written_at_lome_time(): void
    {
        $start = CarbonImmutable::parse('2026-10-06 11:15', 'Africa/Porto-Novo');

        $this->assertSame('20261006T101500/20261006T110000', self::params(GoogleCalendar::url('Test', $start, $start->addMinutes(45)))['dates']);
    }

    public function test_manage_page_and_email_offer_google_calendar_with_the_meet_link(): void
    {
        [$consultation, $token] = $this->booked('2026-10-06 10:15');

        $url = ConsultationCalendar::googleUrl($consultation, self::MEET);
        $query = self::params($url);
        $this->assertSame('20261006T101500/20261006T104500', $query['dates']);
        $this->assertStringContainsString(self::MEET, $query['details']);
        $this->assertStringContainsString($consultation->manageUrl(), $query['details']);

        $this->get(route('consultation.manage', $token))
            ->assertOk()
            ->assertSee('Ajouter à Google Agenda')
            ->assertSee(e($url), false)
            ->assertSee(route('consultation.calendar', $token));

        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'confirmed' && str_contains($mail->render(), 'Ajouter à Google Agenda'));
    }

    public function test_the_google_link_is_never_on_a_public_page(): void
    {
        $this->booked('2026-10-06 10:15');

        $this->get(route('consultation'))->assertOk()->assertDontSee('calendar.google.com');
        $this->get(route('home'))->assertOk()->assertDontSee('calendar.google.com');
    }
}
