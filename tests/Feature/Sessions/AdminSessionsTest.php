<?php

namespace Tests\Feature\Sessions;

use App\Enums\PurchaseStatus;
use App\Enums\RegistrationStatus;
use App\Mail\LiveSessionMail;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\CourseModule;
use App\Models\LiveSession;
use App\Models\LiveSessionRegistration;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

class AdminSessionsTest extends SessionTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function onlineForm(array $overrides = []): array
    {
        return [
            'formation_id' => $this->formation->id,
            'format' => 'online',
            'title' => 'Questions-réponses',
            'date' => now()->addDays(4)->format('Y-m-d'),
            'time' => '10:00',
            'duration' => 90,
            'meet_url' => 'meet.google.com/abc-defg-hij',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function onsiteForm(array $overrides = []): array
    {
        return [
            'formation_id' => $this->formation->id,
            'format' => 'onsite',
            'date' => now()->addDays(6)->format('Y-m-d'),
            'time' => '09:30',
            'duration' => 180,
            'location' => 'Adidogomé, Lycée moderne 2, Lomé',
            'capacity' => 15,
            'price_type' => 'supplement',
            'supplement_amount' => 5000,
            ...$overrides,
        ];
    }

    public function test_sessions_screens_are_for_the_team_only(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get(route('admin.sessions.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.sessions.store'), $this->onlineForm())->assertForbidden();
        $this->actingAs($this->moderator())->get(route('admin.sessions.create'))->assertOk()->assertSee('Adidogomé, Lycée moderne 2, Lomé');
        $this->assertSame(0, LiveSession::query()->count());
    }

    public function test_moderator_creates_an_online_session_announced_and_pinned_in_the_group(): void
    {
        $student = $this->student();
        $moderator = $this->moderator();

        $this->actingAs($moderator)->post(route('admin.sessions.store'), $this->onlineForm())->assertRedirect();

        $session = LiveSession::query()->firstOrFail();
        $this->assertSame('https://meet.google.com/abc-defg-hij', $session->meet_url);
        $this->assertSame('10:00', $session->starts_at->format('H:i'));
        $this->assertTrue($session->ends_at->equalTo($session->starts_at->addMinutes(90)));

        $announcement = ChatMessage::query()->findOrFail($session->announcement_message_id);
        $this->assertTrue($announcement->isPinned());
        $this->assertSame(ChatGroup::forFormation($this->formation)->id, $announcement->chat_group_id);
        $this->assertStringContainsString('Lien Google Meet : https://meet.google.com/abc-defg-hij', (string) $announcement->body);
        $this->assertStringContainsString('heure de Lomé (GMT)', (string) $announcement->body);

        $this->actingAs($student)->get(route('chat.show', $this->formation))->assertSee('Message épinglé')->assertSee('Questions-réponses');
    }

    public function test_meet_link_and_onsite_fields_are_checked(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onlineForm(['meet_url' => 'https://zoom.us/j/123']))->assertSessionHasErrors('meet_url');
        $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onlineForm(['date' => now()->subDay()->format('Y-m-d')]))->assertSessionHasErrors('time');
        $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onsiteForm(['capacity' => '', 'supplement_amount' => '']))->assertSessionHasErrors(['capacity', 'supplement_amount']);
        $this->assertSame(0, LiveSession::query()->count());

        $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onsiteForm())->assertRedirect();
        $session = LiveSession::query()->firstOrFail();
        $this->assertTrue($session->requiresPayment());
        $this->assertNull($session->meet_url);
        $this->assertStringContainsString('Inscription obligatoire', (string) $session->announcement?->body);
    }

    public function test_important_change_notifies_the_students_but_not_a_title_change(): void
    {
        $student = $this->student();
        $outsider = User::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onlineForm());
        $session = LiveSession::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.sessions.update', $session), $this->onlineForm(['title' => 'Nouveau titre']))->assertRedirect();
        Mail::assertNotQueued(LiveSessionMail::class);

        $this->actingAs($admin)->put(route('admin.sessions.update', $session), $this->onlineForm(['time' => '15:00', 'meet_url' => 'https://meet.google.com/xyz-abcd-efg']))->assertRedirect();

        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($student->email) && $mail->kind === 'changed' && in_array('la date ou l\'heure', $mail->changes, true) && in_array('le lien Google Meet', $mail->changes, true));
        Mail::assertNotQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($outsider->email));
        $this->assertStringContainsString('xyz-abcd-efg', (string) $session->refresh()->announcement?->body);
        $this->assertStringContainsString("15\u{00A0}h\u{00A0}00", (string) $session->announcement?->body);
    }

    public function test_cancelling_notifies_registered_students_unpins_and_lists_supplements_to_refund(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onsiteForm(['capacity' => 1]));
        $session = LiveSession::query()->firstOrFail();
        $registered = $this->student();
        $waiting = $this->student();
        $notRegistered = $this->student();
        $this->actingAs($registered)->post(route('student.sessions.register', $session));
        $this->actingAs($registered)->post(route('student.sessions.pay', $session), [
            'payment_method' => 'tg-mixx', 'payer_phone' => '90 12 34 56', 'payer_name' => 'Afi', 'transaction_id' => 'MP1.A1',
            'proof' => UploadedFile::fake()->image('c.png', 600, 1200),
        ]);
        $this->actingAs($waiting)->post(route('student.sessions.register', $session));

        $this->actingAs($admin)->post(route('admin.sessions.cancel', $session), ['reason' => 'Formateur malade'])->assertRedirect();

        $session->refresh();
        $this->assertTrue($session->isCancelled());
        $this->assertFalse($session->announcement?->isPinned());
        $this->assertSame(0, LiveSessionRegistration::query()->where('status', '!=', RegistrationStatus::Cancelled->value)->count());
        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($registered->email) && $mail->kind === 'cancelled');
        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($waiting->email) && $mail->kind === 'cancelled');
        Mail::assertNotQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($notRegistered->email));
        $this->assertTrue(ChatMessage::query()->where('body', 'like', '❌ Session annulée%')->exists());

        $this->actingAs($admin)->get(route('admin.sessions.show', $session))->assertSee('Suppléments à rembourser');
        $purchase = Purchase::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.sessions.refund', [$session, $purchase]))->assertRedirect();
        $this->assertSame(PurchaseStatus::Refunded, $purchase->refresh()->status);
        $this->assertTrue($registered->isEnrolledIn($this->formation));
    }

    public function test_announcement_is_unpinned_after_the_session(): void
    {
        $this->actingAs($this->admin())->post(route('admin.sessions.store'), $this->onlineForm());
        $session = LiveSession::query()->firstOrFail();

        $this->artisan('app:process-live-sessions')->assertSuccessful();
        $this->assertTrue($session->refresh()->announcement?->isPinned());

        $this->travelTo($session->ends_at->addMinute());
        $this->artisan('app:process-live-sessions')->assertSuccessful();
        $this->assertFalse($session->refresh()->announcement?->isPinned());
        $this->assertNotNull($session->announcement_unpinned_at);
    }

    public function test_attendance_sheet_and_history(): void
    {
        $session = $this->onsite(capacity: 5);
        $present = $this->student(['name' => 'Kodjo Présent']);
        $absent = $this->student(['name' => 'Ama Absente']);
        foreach ([$present, $absent] as $student) {
            $this->actingAs($student)->post(route('student.sessions.register', $session));
        }
        $moderator = $this->moderator();

        $this->actingAs($moderator)->get(route('admin.sessions.attendance', $session))
            ->assertOk()
            ->assertSee('Feuille de présence')
            ->assertSeeInOrder(['Ama Absente', 'Kodjo Présent'])
            ->assertSee('window.print()', false);

        $registration = LiveSessionRegistration::query()->where('user_id', $present->id)->firstOrFail();
        $this->actingAs($moderator)->post(route('admin.sessions.attendance.toggle', [$session, $registration]))->assertRedirect();
        $this->assertNotNull($registration->refresh()->attended_at);

        $this->travelTo($session->ends_at->addDay());
        $this->actingAs($moderator)->get(route('admin.sessions.index'))->assertSeeInOrder(['Historique', 'Session en présentiel', '1 présent(s)']);
    }

    public function test_recording_opens_the_prefilled_lesson_form_for_the_admin(): void
    {
        $session = $this->online(['title' => 'Atelier automatisation', 'starts_at' => now()->subHours(3)]);
        $module = CourseModule::factory()->for($this->formation)->create();

        $this->actingAs($this->moderator())->get(route('admin.sessions.recording', $session))->assertForbidden();

        $response = $this->actingAs($this->admin())->get(route('admin.sessions.recording', $session));
        $response->assertRedirect();
        $this->assertStringStartsWith(route('admin.lessons.create', $module), (string) $response->headers->get('Location'));

        $this->actingAs($this->admin())->get((string) $response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Replay : Atelier automatisation du '.$session->starts_at->translatedFormat('j F Y'));
    }

    public function test_the_session_meet_link_is_cleaned_before_being_checked(): void
    {
        $admin = $this->admin();
        $pasted = [
            "  https://meet.google.com/abc-defg-hij\n",
            "\u{FEFF}https://meet.google.com/abc-defg-hij\u{200B}",
            'HTTPS://meet.google.com/ABC-DEFG-HIJ',
            "Réunion Lomé IA School\nRejoindre avec Google Meet : https://meet.google.com/abc-defg-hij",
            'meet.google.com/abc-defg-hij',
        ];

        foreach ($pasted as $value) {
            $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onlineForm(['meet_url' => $value]))->assertSessionHasNoErrors();
        }

        $this->assertSame(['https://meet.google.com/abc-defg-hij'], LiveSession::query()->pluck('meet_url')->unique()->values()->all());

        $this->actingAs($admin)->post(route('admin.sessions.store'), $this->onlineForm(['meet_url' => 'https://zoom.us/j/123']))
            ->assertSessionHasErrors(['meet_url' => 'Le lien Google Meet doit ressembler à https://meet.google.com/abc-defg-hij. Dans Google Meet, touchez « Partager les informations de connexion » ou « Copier le lien ».']);
        $this->assertSame(5, LiveSession::query()->count());
    }
}
