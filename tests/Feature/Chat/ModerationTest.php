<?php

namespace Tests\Feature\Chat;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\ChatMessageReport;
use App\Models\Formation;
use App\Models\User;

class ModerationTest extends ChatTestCase
{
    public function test_moderator_deletes_any_message(): void
    {
        $message = $this->message($this->student(), 'Message déplacé');

        $this->actingAs($this->moderator())->deleteJson($this->url('chat.messages.destroy', ['message' => $message]))->assertOk();

        $this->assertTrue($message->refresh()->isDeleted());
    }

    public function test_only_the_team_pins_messages_shown_at_the_top(): void
    {
        $student = $this->student();
        $message = $this->message($this->admin(), 'Session en direct samedi : https://meet.google.com/abc-defg-hij');

        $this->actingAs($student)->postJson($this->url('chat.messages.pin', ['message' => $message]))->assertForbidden();
        $this->actingAs($this->moderator())->postJson($this->url('chat.messages.pin', ['message' => $message]))->assertOk();

        $this->assertTrue($message->refresh()->isPinned());
        $this->actingAs($student)->get($this->url('chat.show'))
            ->assertSeeInOrder(['Message épinglé', 'https://meet.google.com/abc-defg-hij', 'id="chat-messages"'], false);

        $this->actingAs($this->admin())->postJson($this->url('chat.messages.pin', ['message' => $message]))->assertOk();
        $this->assertFalse($message->refresh()->isPinned());
    }

    public function test_pinned_messages_are_limited(): void
    {
        config(['chat.max_pinned' => 2]);
        $moderator = $this->moderator();
        $messages = collect(range(1, 3))->map(fn (int $i) => $this->message($moderator, 'Annonce '.$i));

        foreach ($messages as $i => $message) {
            $this->travel($i)->seconds();
            $this->actingAs($moderator)->postJson($this->url('chat.messages.pin', ['message' => $message]))->assertOk();
        }

        $this->assertSame([$messages[1]->id, $messages[2]->id], ChatMessage::query()->whereNotNull('pinned_at')->orderBy('id')->pluck('id')->all());
    }

    public function test_announcements_only_mode_lets_only_the_team_write(): void
    {
        $student = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.announcements'))->assertForbidden();
        $this->actingAs($this->moderator())->post($this->url('chat.announcements'))->assertRedirect();
        $this->assertTrue($this->group()->refresh()->announcements_only);

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Bonjour'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'Seule l\'équipe peut écrire dans ce groupe pour le moment (mode annonces).']);
        $this->actingAs($student)->get($this->url('chat.show'))->assertSee('mode annonces');

        $this->actingAs($this->admin())->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Annonce officielle'])->assertCreated();
    }

    public function test_muted_member_reads_but_cannot_write_until_the_end_of_the_mute(): void
    {
        $student = $this->student(['name' => 'Mawuli Bruyant']);

        $this->actingAs($this->moderator())->postJson($this->url('chat.members.mute', ['user' => $student]), ['hours' => 24])
            ->assertOk()
            ->assertJson(['status' => 'Mawuli Bruyant est en sourdine pour 24 heures.']);

        $this->actingAs($student)->get($this->url('chat.show'))->assertOk()->assertSee('Vous êtes en sourdine');
        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Encore moi'])->assertUnprocessable();

        $this->travel(25)->hours();
        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Je peux de nouveau écrire'])->assertCreated();
    }

    public function test_mute_can_be_lifted_and_duration_must_be_one_of_the_choices(): void
    {
        $student = $this->student();
        $moderator = $this->moderator();

        $this->actingAs($moderator)->postJson($this->url('chat.members.mute', ['user' => $student]), ['hours' => 99999])->assertUnprocessable();
        $this->actingAs($moderator)->postJson($this->url('chat.members.mute', ['user' => $student]), ['hours' => 1])->assertOk();
        $this->actingAs($moderator)->deleteJson($this->url('chat.members.unmute', ['user' => $student]))->assertOk();

        $this->assertNull(ChatGroupMember::query()->where('user_id', $student->id)->value('muted_until'));
    }

    public function test_excluded_member_loses_access_and_can_be_reinstated(): void
    {
        $student = $this->student();
        $moderator = $this->moderator();

        $this->actingAs($moderator)->postJson($this->url('chat.members.exclude', ['user' => $student]))->assertOk();

        $this->actingAs($student)->get($this->url('chat.show'))->assertForbidden();
        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Coucou'])->assertForbidden();
        $this->actingAs($moderator)->get($this->url('chat.members'))->assertSee('Membres exclus');

        $this->actingAs($moderator)->post($this->url('chat.members.reinstate', ['user' => $student]))->assertRedirect();
        $this->actingAs($student)->get($this->url('chat.show'))->assertOk();
    }

    public function test_team_members_cannot_be_muted_or_excluded_and_students_cannot_moderate(): void
    {
        $student = $this->student();
        $other = $this->student();
        $moderator = $this->moderator();
        $this->actingAs($moderator)->get($this->url('chat.show'));

        $this->actingAs($this->admin())->postJson($this->url('chat.members.exclude', ['user' => $moderator]))->assertForbidden();
        $this->actingAs($student)->postJson($this->url('chat.members.mute', ['user' => $other]), ['hours' => 1])->assertForbidden();
        $this->actingAs($student)->postJson($this->url('chat.members.exclude', ['user' => $other]))->assertForbidden();
    }

    public function test_reports_are_handled_by_deleting_or_dismissing(): void
    {
        $spam = $this->message($this->student(), 'Publicité');
        $fine = $this->message($this->student(), 'Question normale');
        $reporter = $this->student();
        $spamReport = ChatMessageReport::query()->create(['chat_message_id' => $spam->id, 'user_id' => $reporter->id, 'reason' => 'spam']);
        $fineReport = ChatMessageReport::query()->create(['chat_message_id' => $fine->id, 'user_id' => $reporter->id, 'reason' => 'other']);
        $moderator = $this->moderator();

        $this->actingAs($moderator)->post(route('moderation.reports.handle', $spamReport), ['action' => 'delete'])->assertRedirect();
        $this->actingAs($moderator)->post(route('moderation.reports.handle', $fineReport), ['action' => 'dismiss'])->assertRedirect();

        $this->assertTrue($spam->refresh()->isDeleted());
        $this->assertFalse($fine->refresh()->isDeleted());
        $this->assertSame(ReportStatus::Resolved, $spamReport->refresh()->status);
        $this->assertSame(ReportStatus::Dismissed, $fineReport->refresh()->status);
        $this->assertSame($moderator->id, $spamReport->handled_by);
    }

    public function test_rules_are_shown_on_entry_until_accepted_and_again_after_a_change(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get($this->url('chat.show'))->assertSee("if (showRules) dialog = 'rules'", false);
        $this->actingAs($student)->postJson($this->url('chat.rules.seen'))->assertOk();
        $this->actingAs($student)->get($this->url('chat.show'))->assertDontSee("if (showRules) dialog = 'rules'", false);

        $this->travel(1)->minutes();
        $this->actingAs($this->admin())->put(route('admin.chat.rules', $this->group()), ['rules' => 'Nouvelles règles : restez courtois.'])->assertRedirect();

        $this->actingAs($student)->get($this->url('chat.show'))
            ->assertSee("if (showRules) dialog = 'rules'", false)
            ->assertSee('Nouvelles règles : restez courtois.');
    }

    public function test_rules_can_be_applied_to_every_group_by_the_admin_only(): void
    {
        $second = Formation::factory()->create();

        $this->actingAs($this->moderator())->put(route('admin.chat.rules', $this->group()), ['rules' => 'Piratage'])->assertForbidden();
        $this->actingAs($this->admin())->put(route('admin.chat.rules', $this->group()), ['rules' => 'Règles communes', 'apply_to_all' => '1'])->assertRedirect();

        $this->assertSame('Règles communes', $this->group()->refresh()->rules);
        $this->assertSame('Règles communes', $second->chatGroup?->refresh()->rules);
    }

    public function test_admin_gives_and_removes_the_moderator_role(): void
    {
        $admin = $this->admin();
        $future = User::factory()->create(['email' => 'esi@example.com', 'name' => 'Esi Amevor']);

        $this->actingAs($future)->get(route('admin.moderators.index'))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.moderators.store'), ['email' => 'inconnu@example.com', 'current_password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->post(route('admin.moderators.store'), ['email' => 'ESI@example.com', 'current_password' => 'password'])->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Moderator, $future->refresh()->role);
        $this->assertTrue(ChatGroupMember::query()->where('user_id', $future->id)->where('chat_group_id', $this->group()->id)->whereNull('removed_at')->exists());

        $this->message($future, 'Je suis là pour vous aider');
        $this->actingAs($this->student())->get($this->url('chat.show'))->assertSee('Équipe');
        $this->actingAs($admin)->get(route('admin.moderators.index'))->assertSee('Esi Amevor');

        $this->actingAs($admin)->delete(route('admin.moderators.destroy', $future), ['current_password' => 'password'])->assertRedirect();
        $this->assertSame(UserRole::Student, $future->refresh()->role);
        $this->assertFalse(ChatGroupMember::query()->where('user_id', $future->id)->whereNull('removed_at')->exists());
        $this->actingAs($future)->get($this->url('chat.show'))->assertForbidden();
    }

    public function test_admin_badge_is_trainer(): void
    {
        $this->message($this->admin(), 'Bienvenue');

        $this->actingAs($this->student())->get($this->url('chat.show'))->assertSee('Formateur');
    }

    public function test_admin_group_list_shows_members_activity_and_disk_usage(): void
    {
        $student = $this->student();
        $message = $this->message($student, 'Bonjour', ['type' => 'file', 'attachment_path' => 'x.pdf', 'attachment_name' => 'x.pdf', 'attachment_mime' => 'application/pdf', 'attachment_size' => 3 * 1024 * 1024]);
        foreach ([$this->student(), $this->student()] as $reporter) {
            ChatMessageReport::query()->create(['chat_message_id' => $message->id, 'user_id' => $reporter->id, 'reason' => 'spam']);
        }

        $this->actingAs($student)->get(route('admin.chat.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.chat.index'))
            ->assertOk()
            ->assertSee($this->formation->title)
            ->assertSee('3,0 Mo')
            ->assertSee('1 fichier')
            ->assertViewHas('openReports', 1);
    }
}
