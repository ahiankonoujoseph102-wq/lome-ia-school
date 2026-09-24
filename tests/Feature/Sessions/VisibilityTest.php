<?php

namespace Tests\Feature\Sessions;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;

class VisibilityTest extends SessionTestCase
{
    private const string MEET = 'https://meet.google.com/abc-defg-hij';

    public function test_meet_link_is_never_on_public_pages(): void
    {
        $session = $this->online(['title' => 'Questions-réponses']);

        $this->get(route('formations.show', $this->formation))
            ->assertOk()
            ->assertSee('Prochaine session')
            ->assertSee('Questions-réponses')
            ->assertSee('heure de Lomé (GMT)')
            ->assertSee('au Bénin (GMT+1)')
            ->assertSee('Acheter la formation pour participer')
            ->assertDontSee('meet.google.com')
            ->assertDontSee('abc-defg-hij');

        $this->get(route('home'))->assertOk()->assertSee('Prochaines sessions')->assertSee('Questions-réponses')->assertDontSee('meet.google.com');

        // Connecté mais sans la formation : toujours aucun lien.
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('formations.show', $this->formation))->assertDontSee('meet.google.com');
        $this->actingAs($outsider)->get(route('student.sessions.show', $session))->assertForbidden();
        $this->actingAs($outsider)->get(route('student.sessions.calendar', $session))->assertForbidden();
        $this->actingAs($outsider)->get(route('student.sessions.join', $session))->assertForbidden();
        $this->actingAs($outsider)->get(route('student.sessions.index'))->assertOk()->assertDontSee('Questions-réponses');
        $this->actingAs($outsider)->get(route('dashboard'))->assertDontSee('meet.google.com');
    }

    public function test_home_has_no_sessions_block_without_upcoming_session(): void
    {
        $this->online(['starts_at' => now()->subDays(2)]);
        $this->online(['starts_at' => now()->addDays(2)])->forceFill(['cancelled_at' => now()])->save();

        $this->get(route('home'))->assertOk()->assertDontSee('data-test="home-sessions"', false);
        $this->get(route('formations.show', $this->formation))->assertDontSee('data-test="next-session"', false);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $session = $this->online();

        $this->get(route('student.sessions.show', $session))->assertRedirect(route('login'));
    }

    public function test_buyers_see_the_link_including_provisional_access(): void
    {
        $session = $this->online();
        $purchase = Purchase::factory()->for($this->formation)->status(PurchaseStatus::Provisional)->create();
        app(PurchaseManager::class)->grantAccess($purchase);

        $this->actingAs($purchase->user)->get(route('student.sessions.show', $session))->assertOk()->assertSee(self::MEET);
        $this->actingAs($purchase->user)->get(route('dashboard'))->assertOk()->assertSee('Sessions en direct ou à Lomé')->assertSee('Rejoindre');
        $this->actingAs($purchase->user)->get(route('student.formations.show', $this->formation))->assertSee('data-test="formation-session"', false);
        $this->actingAs($this->moderator())->get(route('student.sessions.show', $session))->assertOk()->assertSee(self::MEET);
    }

    public function test_access_removed_hides_the_session_again(): void
    {
        $session = $this->online();
        $purchase = Purchase::factory()->for($this->formation)->status(PurchaseStatus::Provisional)->create();
        $manager = app(PurchaseManager::class);
        $manager->grantAccess($purchase);

        $manager->reject($purchase, $this->admin(), 'Paiement non reçu.');

        $this->actingAs($purchase->user)->get(route('student.sessions.show', $session))->assertForbidden();
    }

    public function test_join_button_is_active_only_fifteen_minutes_before(): void
    {
        $student = $this->student();
        $session = $this->online(['starts_at' => now()->addHour()->startOfMinute(), 'duration_minutes' => 60]);

        $this->actingAs($student)->get(route('student.sessions.show', $session))
            ->assertSee('data-test="join-button-disabled"', false)
            ->assertDontSee('data-test="join-button"', false);
        $this->actingAs($student)->get(route('student.sessions.join', $session))->assertRedirect(route('student.sessions.show', $session));

        $this->travelTo($session->starts_at->copy()->subMinutes(16));
        $this->assertFalse($session->fresh()?->canJoinNow());

        $this->travelTo($session->starts_at->copy()->subMinutes(15));
        $this->actingAs($student)->get(route('student.sessions.show', $session))->assertSee('data-test="join-button"', false);
        $this->actingAs($student)->get(route('student.sessions.join', $session))->assertRedirect(self::MEET);

        $this->travelTo($session->ends_at->copy()->addMinute());
        $this->actingAs($student)->get(route('student.sessions.join', $session))->assertRedirect(route('student.sessions.show', $session));
    }

    public function test_calendar_file(): void
    {
        $student = $this->student();
        $session = $this->online(['title' => 'Atelier, pratique ; questions', 'starts_at' => now()->addDays(5)->setTime(10, 0), 'duration_minutes' => 90]);

        $response = $this->actingAs($student)->get(route('student.sessions.calendar', $session))->assertOk();

        $this->assertStringStartsWith('text/calendar', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="session-automatisation-avec-lia-', (string) $response->headers->get('Content-Disposition'));
        $ics = (string) $response->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('DTSTART:'.$session->starts_at->format('Ymd').'T100000Z', $ics);
        $this->assertStringContainsString('DTEND:'.$session->starts_at->format('Ymd').'T113000Z', $ics);
        $this->assertStringContainsString('SUMMARY:Atelier\, pratique \; questions', $ics);
        $this->assertStringContainsString('URL:'.self::MEET, $ics);
        $this->assertStringContainsString("END:VCALENDAR\r\n", $ics);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
    }

    public function test_terms_explain_sessions_registration_and_supplement(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Sessions ponctuelles en direct et en présentiel')
            ->assertSee('liste d\'attente', false)
            ->assertSee('jusqu\'à 24 heures avant le début', false)
            ->assertSee('le supplément éventuellement payé est remboursé');
    }
}
