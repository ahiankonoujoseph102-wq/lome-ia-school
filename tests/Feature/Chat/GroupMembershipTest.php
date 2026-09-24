<?php

namespace Tests\Feature\Chat;

use App\Chat\GroupMembership;
use App\Enums\ChatRemovalReason;
use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use Illuminate\Support\Facades\Mail;

class GroupMembershipTest extends ChatTestCase
{
    private function isActiveMember(User $user, ?Formation $formation = null): bool
    {
        return ChatGroupMember::query()
            ->where('chat_group_id', $this->group($formation)->id)
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->exists();
    }

    public function test_each_formation_gets_its_group_automatically(): void
    {
        $formation = Formation::factory()->create();

        $this->assertNotNull($formation->chatGroup);
        $this->assertSame(1, ChatGroup::query()->where('formation_id', $formation->id)->count());
        $this->assertNotEmpty($formation->chatGroup->rules);
    }

    public function test_student_joins_the_group_on_provisional_access_and_leaves_it_when_payment_is_rejected(): void
    {
        Mail::fake();
        $manager = app(PurchaseManager::class);
        $purchase = Purchase::factory()->for($this->formation)->status(PurchaseStatus::Provisional)->create();

        $manager->grantAccess($purchase);
        $this->assertTrue($this->isActiveMember($purchase->user));

        $manager->reject($purchase, $this->admin(), 'Paiement non reçu sur notre compte.');

        $this->assertFalse($this->isActiveMember($purchase->user));
        $this->assertSame(ChatRemovalReason::AccessRevoked, ChatGroupMember::query()->where('user_id', $purchase->user_id)->first()?->removal_reason);
        $this->actingAs($purchase->user)->get($this->url('chat.show'))->assertForbidden();
    }

    public function test_student_joins_the_group_when_a_pending_payment_is_validated(): void
    {
        Mail::fake();
        $purchase = Purchase::factory()->for($this->formation)->status(PurchaseStatus::Pending)->create();
        $this->assertFalse($this->isActiveMember($purchase->user));

        app(PurchaseManager::class)->validate($purchase, $this->admin());

        $this->assertTrue($this->isActiveMember($purchase->user));
    }

    public function test_blocked_account_leaves_every_group_immediately_and_comes_back_when_unblocked(): void
    {
        $other = Formation::factory()->create();
        $student = $this->student();
        app(GroupMembership::class)->add($student, $other);
        $student->enroll($other, EnrollmentSource::Admin);

        app(PurchaseManager::class)->block($student);

        $this->assertFalse($this->isActiveMember($student));
        $this->assertFalse($this->isActiveMember($student, $other));

        app(PurchaseManager::class)->unblock($student->refresh());

        $this->assertTrue($this->isActiveMember($student));
        $this->assertTrue($this->isActiveMember($student, $other));
    }

    public function test_excluded_member_is_not_brought_back_by_a_new_access(): void
    {
        $student = $this->student();
        $member = ChatGroupMember::query()->where('user_id', $student->id)->firstOrFail();

        app(GroupMembership::class)->exclude($member);
        app(GroupMembership::class)->add($student, $this->formation);

        $this->assertFalse($this->isActiveMember($student));
    }

    public function test_admin_and_moderators_are_members_of_every_group(): void
    {
        $second = Formation::factory()->create();
        $admin = $this->admin();
        $moderator = $this->moderator();

        $this->actingAs($admin)->get(route('chat.show', $second))->assertOk();
        $this->actingAs($moderator)->get(route('chat.index'))->assertOk()->assertSee($this->formation->title)->assertSee($second->title);

        $this->assertTrue($this->isActiveMember($moderator));
        $this->assertTrue($this->isActiveMember($moderator, $second));
    }

    public function test_access_given_or_removed_by_command_updates_the_group(): void
    {
        $student = User::factory()->create(['email' => 'eleve@example.com']);

        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => 'automatisation-ia'])->assertSuccessful();
        $this->assertTrue($this->isActiveMember($student));

        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => 'automatisation-ia', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($this->isActiveMember($student));
    }

    public function test_new_member_sees_history_without_it_counting_as_unread(): void
    {
        $admin = $this->admin();
        $this->message($admin, 'Annonce déjà publiée');

        $student = $this->student();

        $this->actingAs($student)->get($this->url('chat.show'))
            ->assertOk()
            ->assertSee('Annonce déjà publiée')
            ->assertDontSee('message non lu');
    }
}
