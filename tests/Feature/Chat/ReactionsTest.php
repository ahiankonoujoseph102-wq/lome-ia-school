<?php

namespace Tests\Feature\Chat;

use App\Contracts\PushSender;
use App\Enums\ChatReaction;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\Formation;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakePushSender;
use Tests\Fakes\RecordingBroadcaster;

/**
 * Réactions par emoji : six emojis, une seule réaction par personne et par message (un autre emoji remplace,
 * le même retire), compteurs sous le message, aucune notification, interdites aux membres en sourdine et retirés.
 */
class ReactionsTest extends ChatTestCase
{
    private function react(User $user, ChatMessage $message, string $emoji): TestResponse
    {
        return $this->actingAs($user)->postJson($this->url('chat.messages.react', ['message' => $message]), ['emoji' => $emoji]);
    }

    public function test_one_reaction_per_person_replaced_by_another_emoji_and_removed_by_the_same(): void
    {
        $author = $this->student(['name' => 'Kossi Agbeko']);
        $reader = $this->student(['name' => 'Ama Lawson']);
        $message = $this->message($author, 'Merci pour la séance !');

        $this->react($reader, $message, 'like')->assertOk()->assertJson(['reaction' => 'like']);
        $this->assertSame(['like' => 1], $message->fresh()->load('reactions')->reactionCounts());

        // Un autre emoji remplace la réaction (toujours une seule ligne).
        $this->react($reader, $message, 'love')->assertOk()->assertJson(['reaction' => 'love']);
        $this->assertSame(1, ChatMessageReaction::query()->count());
        $this->assertSame(['love' => 1], $message->fresh()->load('reactions')->reactionCounts());

        // Le même emoji retire la réaction.
        $this->react($reader, $message, 'love')->assertOk()->assertJson(['reaction' => null]);
        $this->assertSame(0, ChatMessageReaction::query()->count());
    }

    public function test_counters_are_shown_under_the_message_in_the_order_of_the_six_emojis(): void
    {
        $author = $this->student();
        $message = $this->message($author, 'Voici ma capture');

        $this->react($this->student(), $message, 'clap');
        $this->react($this->student(), $message, 'like');
        $this->react($this->student(), $message, 'like');
        $viewer = $this->student();
        $response = $this->react($viewer, $message, 'laugh')->assertOk();

        $this->assertSame(['like' => 2, 'laugh' => 1, 'clap' => 1], $message->fresh()->load('reactions')->reactionCounts());

        // Le message renvoyé a ses compteurs, la réaction de la personne est mise en avant.
        $html = (string) $response->json('html');
        $this->assertStringContainsString('data-test="reactions"', $html);
        $this->assertStringContainsString('👍</span><span class="tabular-nums">2</span>', $html);
        $this->assertMatchesRegularExpression('/chat-reaction-mine[^>]*data-react="laugh"/', $html);

        // Dans la conversation : les compteurs et la barre des six emojis du menu.
        $page = $this->actingAs($viewer)->get($this->url('chat.show'))->assertOk();
        $page->assertSee('data-test="reactions"', false)->assertSee('data-my-reaction="laugh"', false);
        foreach (ChatReaction::cases() as $reaction) {
            $page->assertSee('data-test="react-'.$reaction->value.'"', false);
        }
    }

    public function test_unknown_emoji_is_refused(): void
    {
        $message = $this->message($this->student());

        $this->react($this->student(), $message, 'poop')->assertUnprocessable()->assertJsonValidationErrors(['emoji' => 'Réaction inconnue.']);
        $this->assertSame(0, ChatMessageReaction::query()->count());
    }

    public function test_muted_members_cannot_react_but_the_team_can(): void
    {
        $message = $this->message($this->student());
        $muted = $this->student();
        ChatGroupMember::query()->where('user_id', $muted->id)->update(['muted_until' => now()->addDay()]);

        $this->react($muted, $message, 'like')->assertForbidden();
        $this->actingAs($muted)->get($this->url('chat.show'))->assertSee('data-can-react="0"', false);

        $this->react($this->admin(), $message, 'pray')->assertOk();
        $this->react($this->moderator(), $message, 'clap')->assertOk();
        $this->assertSame(2, ChatMessageReaction::query()->count());

        // Sourdine terminée : la réaction redevient possible.
        $this->travel(25)->hours();
        $this->react($muted, $message, 'like')->assertOk();
    }

    public function test_removed_members_and_outsiders_cannot_react(): void
    {
        $message = $this->message($this->student());
        $excluded = $this->student();
        $this->actingAs($this->moderator())->postJson($this->url('chat.members.exclude', ['user' => $excluded]))->assertOk();

        $this->react($excluded, $message, 'like')->assertForbidden();
        $this->react(User::factory()->create(), $message, 'like')->assertForbidden();
        $this->assertSame(0, ChatMessageReaction::query()->count());
    }

    public function test_announcements_only_mode_still_allows_reactions(): void
    {
        $this->group()->forceFill(['announcements_only' => true])->save();
        $announcement = $this->message($this->admin(), 'Séance ce soir à 20 h');

        $this->react($this->student(), $announcement, 'like')->assertOk();
    }

    public function test_deleted_messages_cannot_be_reacted_to_and_lose_their_reactions(): void
    {
        $author = $this->student();
        $message = $this->message($author);
        $this->react($this->student(), $message, 'like')->assertOk();

        $this->actingAs($author)->deleteJson($this->url('chat.messages.destroy', ['message' => $message]))->assertOk();

        $this->assertSame(0, ChatMessageReaction::query()->count());
        $this->react($this->student(), $message, 'like')->assertForbidden();
    }

    public function test_a_message_from_another_group_is_not_found(): void
    {
        $other = Formation::factory()->create();
        $foreign = ChatGroup::forFormation($other)->messages()->create(['user_id' => $this->admin()->id, 'type' => 'text', 'body' => 'Autre groupe']);

        $this->react($this->student(), $foreign, 'like')->assertNotFound();
    }

    public function test_a_reaction_sends_no_notification_and_no_unread_message(): void
    {
        Mail::fake();
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);

        $author = $this->student();
        $message = $this->message($author, 'Question sur le module 2');
        PushSubscription::query()->create(['user_id' => $author->id, 'endpoint' => 'https://push.example.com/a', 'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/a'), 'public_key' => 'BPublicKey', 'auth_token' => 'auth']);
        ChatGroupMember::query()->where('user_id', $author->id)->update(['last_read_message_id' => $message->id]);
        $messagesBefore = ChatMessage::query()->count();

        $this->react($this->student(), $message, 'love')->assertOk();

        $this->assertSame([], $push->sent);
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $this->assertSame($messagesBefore, ChatMessage::query()->count());
        $this->assertSame(0, $this->group()->unreadMessagesFor(ChatGroupMember::query()->where('user_id', $author->id)->firstOrFail())->count());
    }

    public function test_other_members_receive_the_new_counters_by_the_regular_update(): void
    {
        $author = $this->student();
        $message = $this->message($author, 'Bonsoir');
        $since = now()->subSecond()->toIso8601String();

        $this->travel(2)->seconds();
        $this->react($this->student(), $message, 'wow')->assertOk();

        $updates = $this->actingAs($author)->getJson($this->url('chat.messages.index').'?after='.$message->id.'&since='.urlencode($since))
            ->assertOk()
            ->json('updates');

        $this->assertCount(1, $updates);
        $this->assertSame($message->id, $updates[0]['id']);
        $this->assertStringContainsString('😮', $updates[0]['html']);
    }

    public function test_the_realtime_signal_carries_no_content(): void
    {
        $broadcaster = new RecordingBroadcaster;
        Broadcast::extend('recording', fn () => $broadcaster);
        config(['broadcasting.default' => 'recording', 'broadcasting.connections.recording' => ['driver' => 'recording']]);
        $message = $this->message($this->student());

        $this->react($this->student(), $message, 'like')->assertOk();

        $this->assertCount(1, $broadcaster->broadcasts);
        $this->assertSame('message.updated', $broadcaster->broadcasts[0]['payload']['kind'] ?? null);
        $this->assertStringNotContainsString('like', (string) json_encode($broadcaster->broadcasts));
    }
}
