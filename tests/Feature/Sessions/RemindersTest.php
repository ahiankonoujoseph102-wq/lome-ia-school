<?php

namespace Tests\Feature\Sessions;

use App\Contracts\PushSender;
use App\Mail\LiveSessionMail;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakePushSender;

class RemindersTest extends SessionTestCase
{
    private FakePushSender $push;

    protected function setUp(): void
    {
        parent::setUp();

        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);
    }

    private function device(User $user): void
    {
        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/'.$user->id,
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/'.$user->id),
            'public_key' => 'BKey',
            'auth_token' => 'auth',
        ]);
    }

    public function test_online_reminders_go_to_every_buyer_the_day_before_and_one_hour_before(): void
    {
        $session = $this->online(['starts_at' => now()->addHours(30)]);
        $buyer = $this->student();
        $noEmail = $this->student(['session_email_enabled' => false]);
        $noPush = $this->student(['chat_push_enabled' => false]);
        $outsider = User::factory()->create();
        foreach ([$buyer, $noEmail, $noPush, $outsider] as $user) {
            $this->device($user);
        }

        $this->artisan('app:process-live-sessions')->assertSuccessful();
        Mail::assertNothingQueued();

        // La veille
        $this->travelTo($session->starts_at->subHours(23));
        $this->artisan('app:process-live-sessions')->assertSuccessful();

        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($buyer->email) && $mail->kind === 'reminder_day');
        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($noPush->email));
        Mail::assertNotQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($noEmail->email));
        Mail::assertNotQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($outsider->email));
        $this->assertEqualsCanonicalizing([$buyer->id, $noEmail->id], $this->push->recipients());

        // Pas de doublon au passage suivant du cron
        Mail::fake();
        $this->artisan('app:process-live-sessions')->assertSuccessful();
        Mail::assertNothingQueued();

        // 1 h avant
        $this->travelTo($session->starts_at->subMinutes(55));
        $this->artisan('app:process-live-sessions')->assertSuccessful();
        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($buyer->email) && $mail->kind === 'reminder_hour');
        $this->assertSame(1, collect(Mail::queued(LiveSessionMail::class))->filter(fn (LiveSessionMail $mail) => $mail->hasTo($buyer->email))->count());
    }

    public function test_onsite_reminders_go_to_registered_students_only(): void
    {
        $session = $this->onsite(capacity: 1, attributes: ['starts_at' => now()->addHours(20)]);
        $registered = $this->student();
        $waiting = $this->student();
        $other = $this->student();
        $this->actingAs($registered)->post(route('student.sessions.register', $session));
        $this->actingAs($waiting)->post(route('student.sessions.register', $session));

        $this->artisan('app:process-live-sessions')->assertSuccessful();

        Mail::assertQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($registered->email) && $mail->kind === 'reminder_day');
        Mail::assertNotQueued(LiveSessionMail::class, fn (LiveSessionMail $mail) => $mail->hasTo($waiting->email) || $mail->hasTo($other->email));
    }

    public function test_reminder_email_content_and_cancelled_sessions_are_skipped(): void
    {
        $session = $this->online(['starts_at' => now()->addMinutes(50), 'title' => 'Direct du soir']);
        $cancelled = $this->online(['starts_at' => now()->addMinutes(50)]);
        $cancelled->forceFill(['cancelled_at' => now()])->save();
        $buyer = $this->student(['name' => 'Afi Dossou']);

        $this->artisan('app:process-live-sessions')->assertSuccessful();

        Mail::assertQueued(LiveSessionMail::class, 1);
        Mail::assertQueued(LiveSessionMail::class, function (LiveSessionMail $mail) use ($buyer): bool {
            $html = $mail->render();

            return $mail->hasTo($buyer->email)
                && $mail->kind === 'reminder_hour'
                && str_contains($html, 'commence dans 1 heure')
                && str_contains($html, 'https://meet.google.com/abc-defg-hij')
                && str_contains($html, 'heure de Lomé (GMT)')
                && str_contains($html, 'au Bénin (GMT+1)');
        });
    }

    public function test_session_email_setting_is_in_the_notifications_page(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get(route('student.notifications'))->assertSee('Rappels des sessions par e-mail');
        $this->actingAs($student)->put(route('student.notifications.update'), ['chat_push_enabled' => '1'])->assertRedirect();

        $this->assertFalse($student->refresh()->session_email_enabled);
    }
}
