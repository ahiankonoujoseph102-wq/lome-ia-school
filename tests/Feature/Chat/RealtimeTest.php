<?php

namespace Tests\Feature\Chat;

use App\Chat\ChatRealtime;
use App\Events\Chat\ChatGroupChanged;
use App\Jobs\SendChatPushNotifications;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\RecordingBroadcaster;

class RealtimeTest extends ChatTestCase
{
    private RecordingBroadcaster $broadcaster;

    protected function setUp(): void
    {
        parent::setUp();

        $broadcaster = $this->broadcaster = new RecordingBroadcaster;
        Broadcast::extend('recording', fn () => $broadcaster);
        config([
            'broadcasting.default' => 'recording',
            'broadcasting.connections.recording' => ['driver' => 'recording'],
        ]);
    }

    public function test_new_message_is_broadcast_immediately_without_the_queue(): void
    {
        Queue::fake();
        $student = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'En direct'])->assertCreated();

        // Diffusé pendant la requête elle-même…
        $this->assertCount(1, $this->broadcaster->broadcasts);
        $broadcast = $this->broadcaster->broadcasts[0];
        $this->assertSame(['private-chat.group.'.$this->group()->id], $broadcast['channels']);
        $this->assertSame('chat.changed', $broadcast['event']);
        $this->assertSame(ChatGroupChanged::MESSAGE_CREATED, $broadcast['payload']['kind']);

        // … sans passer par la file d'attente, qui ne reçoit que les notifications sur téléphone.
        Queue::assertNotPushed(BroadcastEvent::class);
        Queue::assertPushed(SendChatPushNotifications::class);
    }

    public function test_broadcast_contains_no_message_content(): void
    {
        $student = $this->student(['phone' => '+22890112233']);

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Contenu privé'])->assertCreated();

        $encoded = (string) json_encode($this->broadcaster->broadcasts);
        $this->assertStringNotContainsString('Contenu privé', $encoded);
        $this->assertStringNotContainsString('90112233', $encoded);
        $this->assertStringNotContainsString($student->email, $encoded);
    }

    public function test_deletion_pin_and_moderation_are_broadcast(): void
    {
        $message = $this->message($this->student(), 'À supprimer');
        $moderator = $this->moderator();

        $this->actingAs($moderator)->postJson($this->url('chat.messages.pin', ['message' => $message]))->assertOk();
        $this->actingAs($moderator)->deleteJson($this->url('chat.messages.destroy', ['message' => $message]))->assertOk();
        $this->actingAs($moderator)->postJson($this->url('chat.announcements'))->assertOk();

        $this->assertSame(
            [ChatGroupChanged::MESSAGE_UPDATED, ChatGroupChanged::MESSAGE_UPDATED, ChatGroupChanged::GROUP_UPDATED],
            array_map(fn (array $broadcast) => $broadcast['payload']['kind'], $this->broadcaster->broadcasts),
        );
    }

    public function test_message_is_saved_even_when_pusher_is_down(): void
    {
        $this->broadcaster->failing = true;
        $student = $this->student();
        $reader = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Malgré la panne'])->assertCreated();

        // Les autres membres le reçoivent par la mise à jour régulière.
        $response = $this->actingAs($reader)->getJson($this->url('chat.messages.index', ['after' => 0]))->assertOk();
        $this->assertStringContainsString('Malgré la panne', $response->json('html'));
    }

    public function test_without_pusher_keys_the_chat_falls_back_to_regular_updates(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => null,
            'broadcasting.connections.pusher.secret' => null,
            'broadcasting.connections.pusher.app_id' => null,
        ]);
        $student = $this->student();

        $this->assertFalse(ChatRealtime::pusherConfigured());
        $this->assertFalse(ChatRealtime::broadcastingEnabled());
        $this->assertNull(ChatRealtime::clientConfig());

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Sans Pusher'])->assertCreated();
        $this->assertSame([], $this->broadcaster->broadcasts);

        // La page du groupe passe en mise à jour régulière (toutes les 4 secondes).
        $page = $this->actingAs($student)->get($this->url('chat.show'))->assertOk();
        $this->assertNull($page->viewData('realtime'));
        $this->assertSame(4, (int) config('chat.poll_interval'));
        $this->assertStringContainsString('Sans Pusher', (string) $this->getJson($this->url('chat.messages.index', ['after' => 0]))->json('html'));
    }

    public function test_with_pusher_keys_the_browser_gets_the_public_key_only(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'cle-publique',
            'broadcasting.connections.pusher.secret' => 'secret-a-ne-jamais-montrer',
            'broadcasting.connections.pusher.app_id' => '123456',
            'broadcasting.connections.pusher.options.cluster' => 'eu',
        ]);

        $this->assertSame(['key' => 'cle-publique', 'cluster' => 'eu'], ChatRealtime::clientConfig());

        $this->actingAs($this->student())->get($this->url('chat.show'))
            ->assertOk()
            ->assertSee('cle-publique')
            ->assertDontSee('secret-a-ne-jamais-montrer');
    }

    public function test_private_channel_is_reserved_to_members(): void
    {
        require base_path('routes/channels.php');
        $channel = 'private-chat.group.'.$this->group()->id;

        $this->actingAs($this->student())->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1'])->assertOk();
        $this->actingAs($this->moderator())->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1'])->assertOk();
        $this->actingAs(User::factory()->create())->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1'])->assertForbidden();
    }
}
