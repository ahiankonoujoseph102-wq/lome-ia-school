<?php

namespace Tests\Feature\Chat;

use App\Chat\MessageReactions;
use App\Chat\UnreadCounter;
use App\Enums\ChatMessageType;
use App\Enums\ChatReaction;
use App\Enums\EnrollmentSource;
use App\Events\FormationAccessGranted;
use App\Models\ChatGroupMember;
use App\Models\Formation;
use Illuminate\Support\Facades\DB;

/**
 * Pastilles de messages non lus (comme sur WhatsApp) : liste des groupes, en-tête du site, Mon espace.
 */
class UnreadBadgesTest extends ChatTestCase
{
    public function test_each_group_shows_its_own_unread_count(): void
    {
        $other = Formation::factory()->create(['slug' => 'chatgpt-claude', 'title' => 'ChatGPT & Claude']);
        $reader = $this->student();
        FormationAccessGranted::dispatch($reader->enroll($other, EnrollmentSource::Admin));
        $author = $this->student(['name' => 'Kodjo Mensah']);
        FormationAccessGranted::dispatch($author->enroll($other, EnrollmentSource::Admin));

        $this->message($author, 'Un');
        $this->message($author, 'Deux');
        $this->message($author, 'Trois');
        $this->group($other)->messages()->create(['user_id' => $author->id, 'type' => ChatMessageType::Text, 'body' => 'Autre groupe']);

        $this->actingAs($reader)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('aria-label="3 non lus"', false)
            ->assertSee('aria-label="1 non lus"', false);
    }

    public function test_deleted_messages_reactions_and_own_messages_do_not_count(): void
    {
        $reader = $this->student();
        $author = $this->student(['name' => 'Kodjo Mensah']);
        $admin = $this->admin();

        $kept = $this->message($author, 'Gardé');
        $this->message($author, 'Supprimé par la modération')->wipe($admin);
        $this->message($reader, 'Mon message');

        // Une réaction à un message déjà lu ne crée aucun non-lu.
        $read = $this->message($author, 'Ancien');
        ChatGroupMember::query()->where('user_id', $reader->id)->update(['last_read_message_id' => $read->id]);
        app(MessageReactions::class)->toggle($read, $author, ChatReaction::Love);

        $this->assertSame(0, app(UnreadCounter::class)->totalFor($reader));

        ChatGroupMember::query()->where('user_id', $reader->id)->update(['last_read_message_id' => $kept->id - 1]);
        // « Gardé » et « Ancien » comptent ; le message supprimé, le sien et la réaction ne comptent pas.
        $this->assertSame(2, app(UnreadCounter::class)->forUser($reader)->sum());
    }

    public function test_the_site_header_and_my_space_show_the_badge_until_the_group_is_opened(): void
    {
        $reader = $this->student();
        $author = $this->student(['name' => 'Kodjo Mensah']);
        $this->message($author, 'Nouveau');
        $this->message($author, 'Encore');

        // Page publique : pastille sur la photo du compte (téléphone), le menu du compte et « Mes groupes ».
        $this->actingAs($reader)->get(route('home'))
            ->assertOk()
            ->assertSee('data-test="header-unread"', false)
            ->assertSee('Mon espace : 2 messages non lus', false)
            ->assertSee('data-test="menu-groups"', false);

        // Mon espace : onglet « Groupes » de la barre du bas.
        $this->actingAs($reader)->get(route('dashboard'))->assertSee('data-test="nav-unread"', false);

        // Ouvrir le groupe remet le compteur à zéro.
        $this->actingAs($reader)->get($this->url('chat.show'))->assertOk();

        $this->actingAs($reader)->get(route('home'))->assertDontSee('data-test="header-unread"', false);
        $this->actingAs($reader)->get(route('dashboard'))->assertDontSee('data-test="nav-unread"', false);
    }

    public function test_a_visitor_never_triggers_the_count(): void
    {
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="header-unread"', false);

        $this->assertEmpty(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'chat_group_members')));
    }

    public function test_the_count_is_a_single_query_per_page(): void
    {
        $reader = $this->student();
        $this->message($this->student(), 'Nouveau');
        $this->actingAs($reader);

        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();

        $counts = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'COUNT(*) as unread'));
        $this->assertCount(1, $counts, 'En-tête et barre de Mon espace partagent la même requête.');
    }
}
