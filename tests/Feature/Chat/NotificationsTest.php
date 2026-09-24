<?php

namespace Tests\Feature\Chat;

use App\Contracts\PushSender;
use App\Jobs\SendChatPushNotifications;
use App\Mail\ChatDigestMail;
use App\Models\ChatGroupMember;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakePushSender;

class NotificationsTest extends ChatTestCase
{
    private function subscribe(User $user, string $endpoint): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => 'BPublicKey',
            'auth_token' => 'auth',
        ]);
    }

    public function test_phone_notifications_go_to_the_right_members(): void
    {
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);

        $author = $this->student(['name' => 'Kodjo Mensah']);
        $wanted = $this->student();
        $pushDisabled = $this->student(['chat_push_enabled' => false]);
        $groupMuted = $this->student();
        $watching = $this->student();
        $expired = $this->student();
        $outsider = User::factory()->create();

        foreach ([$author, $wanted, $pushDisabled, $groupMuted, $watching, $outsider] as $user) {
            $this->subscribe($user, 'https://push.example.com/'.$user->id);
        }
        $this->subscribe($expired, 'https://push.example.com/expire');
        $push->expired[] = 'https://push.example.com/expire';

        ChatGroupMember::query()->where('user_id', $groupMuted->id)->update(['notifications_muted' => true]);
        ChatGroupMember::query()->where('user_id', $watching->id)->update(['last_seen_at' => now()]);

        // Envoi du message : la notification passe par la file d'attente (exécutée ici immédiatement).
        $this->actingAs($author)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Qui vient samedi ?'])->assertCreated();

        $this->assertSame([$wanted->id], $push->recipients());
        $this->assertSame('Automatisation avec l\'IA', $push->sent[0]['payload']['title']);
        $this->assertSame('Kodjo : Qui vient samedi ?', $push->sent[0]['payload']['body']);
        $this->assertSame(route('chat.show', $this->formation), $push->sent[0]['payload']['url']);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/expire')]);
    }

    public function test_no_phone_notification_without_web_push_keys(): void
    {
        $this->assertFalse(app(PushSender::class)->isConfigured());

        $reader = $this->student();
        $this->subscribe($reader, 'https://push.example.com/1');
        $message = $this->message($this->student(), 'Bonjour');

        (new SendChatPushNotifications($message->id))->handle(app(PushSender::class));

        $this->assertNull(PushSubscription::query()->value('last_used_at'));
    }

    public function test_student_registers_and_removes_a_device(): void
    {
        $student = $this->student(['chat_push_enabled' => false]);
        $data = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'BPublic', 'auth' => 'secret'], 'contentEncoding' => 'aes128gcm'];

        $this->actingAs($student)->postJson(route('student.notifications.subscribe'), $data)->assertOk();
        $this->actingAs($student)->postJson(route('student.notifications.subscribe'), $data)->assertOk();

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertTrue($student->refresh()->chat_push_enabled);

        $this->actingAs($student)->postJson(route('student.notifications.subscribe'), ['endpoint' => 'http://pas-securise.example.com', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertUnprocessable();

        $this->actingAs($student)->deleteJson(route('student.notifications.unsubscribe'), ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_notification_settings_are_saved_from_the_profile(): void
    {
        $student = $this->student();
        $groupId = $this->group()->id;

        $this->actingAs($student)->get(route('student.profile'))->assertSee(route('student.notifications'));
        $this->actingAs($student)->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('Notifications sur ce téléphone')
            ->assertSee('E-mail récapitulatif')
            ->assertSee($this->formation->title);

        $this->actingAs($student)->put(route('student.notifications.update'), [
            'chat_push_enabled' => '0',
            'muted_groups' => [$groupId],
        ])->assertRedirect();

        $student->refresh();
        $this->assertFalse($student->chat_push_enabled);
        $this->assertFalse($student->chat_digest_enabled);
        $this->assertTrue((bool) ChatGroupMember::query()->where('user_id', $student->id)->value('notifications_muted'));

        // Couper / réactiver depuis le groupe lui-même.
        $this->actingAs($student)->postJson($this->url('chat.notifications'))->assertOk()->assertJson(['muted' => false]);
    }

    public function test_iphone_explanation_is_shown_when_push_is_available(): void
    {
        config(['services.webpush.public_key' => 'BCle', 'services.webpush.private_key' => 'prive']);
        $this->app->forgetInstance(PushSender::class);

        $this->actingAs($this->student())->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('Sur iPhone, une étape de plus')
            ->assertSee('Sur l\'écran d\'accueil', false)
            ->assertSee('Activer les notifications')
            ->assertDontSee('prive');
    }

    public function test_daily_digest_is_sent_once_with_unread_messages_only(): void
    {
        Mail::fake();
        $reader = $this->student(['name' => 'Afi Dossou']);
        $noDigest = $this->student(['chat_digest_enabled' => false]);
        $author = $this->student(['name' => 'Kodjo Mensah']);
        $this->message($author, 'Première question');
        $this->message($author, 'Deuxième question');

        $this->artisan('app:send-chat-digests')->assertSuccessful();

        Mail::assertQueued(ChatDigestMail::class, 1);
        Mail::assertNotQueued(ChatDigestMail::class, fn (ChatDigestMail $mail) => $mail->hasTo($noDigest->email));
        Mail::assertNotQueued(ChatDigestMail::class, fn (ChatDigestMail $mail) => $mail->hasTo($author->email));
        Mail::assertQueued(ChatDigestMail::class, function (ChatDigestMail $mail) use ($reader): bool {
            $html = $mail->render();

            return $mail->hasTo($reader->email)
                && $mail->groups[0]['count'] === 2
                && str_contains($html, route('chat.show', $this->formation))
                && str_contains($html, 'Kodjo : Deuxième question');
        });

        // Pas une deuxième fois le même jour, même avec un nouveau message.
        Mail::fake();
        $this->message($author, 'Troisième question');
        $this->artisan('app:send-chat-digests')->assertSuccessful();
        Mail::assertNothingQueued();

        // Le lendemain : seulement ce qui n'a pas déjà été annoncé.
        $this->travel(1)->days();
        $this->artisan('app:send-chat-digests')->assertSuccessful();
        Mail::assertQueued(ChatDigestMail::class, fn (ChatDigestMail $mail) => $mail->hasTo($reader->email) && $mail->groups[0]['count'] === 1);
    }

    public function test_no_digest_without_unread_messages_or_for_muted_groups(): void
    {
        Mail::fake();
        $reader = $this->student();
        $muted = $this->student();
        ChatGroupMember::query()->where('user_id', $muted->id)->update(['notifications_muted' => true]);

        $this->artisan('app:send-chat-digests')->assertSuccessful();
        Mail::assertNothingQueued();

        $message = $this->message($this->student(), 'Nouveau');
        ChatGroupMember::query()->where('user_id', $reader->id)->update(['last_read_message_id' => $message->id]);

        $this->artisan('app:send-chat-digests')->assertSuccessful();
        Mail::assertNotQueued(ChatDigestMail::class, fn (ChatDigestMail $mail) => $mail->hasTo($reader->email) || $mail->hasTo($muted->email));
    }

    public function test_digest_and_queue_are_scheduled_for_the_cron(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('app:send-chat-digests')
            ->expectsOutputToContain('queue:work --stop-when-empty')
            ->assertSuccessful();
    }
}
