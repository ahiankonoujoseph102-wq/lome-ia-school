<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Mail\SecurityAlertMail;
use App\Models\AdminActivity;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AdminSecurityTest extends AdminTestCase
{
    /**
     * Pages réservées à l'admin (jamais les modérateurs ni les élèves).
     *
     * @return list<string>
     */
    private function adminOnlyUrls(User $student): array
    {
        return [
            route('admin.index'),
            route('admin.payments.index'),
            route('admin.sales.index'),
            route('admin.promo-codes.index'),
            route('admin.exports.index'),
            route('admin.exports.download', 'ventes'),
            route('admin.formations.index'),
            route('admin.students.index'),
            route('admin.students.show', $student),
            route('admin.settings.index'),
            route('admin.settings.edit', 'paiements'),
            route('admin.testimonials.index'),
            route('admin.activity.index'),
            route('admin.affiliates.withdrawals'),
            route('admin.affiliates.index'),
            route('admin.consultations.index'),
            route('admin.moderators.index'),
        ];
    }

    public function test_two_factor_is_mandatory_for_admin_and_moderators(): void
    {
        $admin = User::factory()->withoutTwoFactor()->create(['role' => UserRole::Admin]);
        $moderator = User::factory()->withoutTwoFactor()->create(['role' => UserRole::Moderator]);
        $this->assertNull($admin->two_factor_confirmed_at);

        $this->actingAs($admin)->get(route('admin.index'))->assertRedirect(route('security.edit'));
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertRedirect(route('security.edit'));
        $this->actingAs($moderator)->get(route('admin.sessions.index'))->assertRedirect(route('security.edit'));
        $this->actingAs($moderator)->get(route('moderation.reports.index'))->assertRedirect(route('security.edit'));
        $this->actingAs($moderator)->getJson(route('moderation.reports.index'))->assertForbidden();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Double authentification obligatoire')
            ->assertSee('Obligatoire');

        // Une fois activée et confirmée par un code : accès normal.
        $admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin->refresh())->get(route('admin.index'))->assertOk();
    }

    public function test_students_never_need_it_and_never_reach_the_admin(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk()->assertSee('Facultative')->assertDontSee('Double authentification obligatoire');

        foreach ($this->adminOnlyUrls($student) as $url) {
            $this->actingAs($student)->get($url)->assertForbidden();
        }

        $this->actingAs($student)->get(route('admin.sessions.index'))->assertForbidden();
        $this->actingAs($student)->get(route('moderation.reports.index'))->assertForbidden();
    }

    public function test_moderators_only_reach_group_moderation_and_sessions(): void
    {
        $moderator = $this->moderator();
        $student = User::factory()->create();

        foreach ($this->adminOnlyUrls($student) as $url) {
            $this->actingAs($moderator)->get($url)->assertForbidden();
        }

        $this->actingAs($moderator)->post(route('admin.students.access', $student), ['formation_id' => 1, 'amount' => 0, 'method' => 'manual-cash', 'reason' => 'Test'])->assertForbidden();
        $this->actingAs($moderator)->post(route('admin.promo-codes.store'), ['code' => 'TEST'])->assertForbidden();

        $this->actingAs($moderator)->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('Messages signalés')
            ->assertDontSee('Codes promo')
            ->assertDontSee('Réglages du site');
        $this->actingAs($moderator)->get(route('moderation.reports.index'))->assertOk();
    }

    public function test_role_changes_need_the_password_and_send_an_alert(): void
    {
        $future = User::factory()->create(['email' => 'futur@exemple.test']);

        $this->actingAs($this->admin)->post(route('admin.moderators.store'), ['email' => 'futur@exemple.test'])->assertSessionHasErrors('current_password');
        $this->actingAs($this->admin)->post(route('admin.moderators.store'), ['email' => 'futur@exemple.test', 'current_password' => 'mauvais'])->assertSessionHasErrors('current_password');
        $this->assertFalse($future->refresh()->isModerator());
        Mail::assertNothingQueued();

        $this->actingAs($this->admin)->post(route('admin.moderators.store'), ['email' => 'futur@exemple.test', 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertTrue($future->refresh()->isModerator());
        Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $mail): bool => $mail->activity->action === AdminAction::RoleChanged && str_contains($mail->activity->description, 'futur@exemple.test'));

        $this->actingAs($this->admin)->delete(route('admin.moderators.destroy', $future))->assertSessionHasErrors('current_password', null, 'demote'.$future->id);
        $this->actingAs($this->admin)->delete(route('admin.moderators.destroy', $future), ['current_password' => 'password'])->assertRedirect();
        $this->assertFalse($future->refresh()->isModerator());
        $this->assertSame(2, AdminActivity::query()->where('action', AdminAction::RoleChanged)->count());
    }

    public function test_activity_log_is_listed_and_filtered(): void
    {
        $other = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Deuxième Admin']);
        $student = User::factory()->create(['name' => 'Élève Exemple']);

        $this->actingAs($this->admin)->post(route('admin.users.block', $student));
        $this->actingAs($other)->post(route('admin.users.unblock', $student));

        $this->actingAs($this->admin)->get(route('admin.activity.index'))
            ->assertOk()
            ->assertSee('Compte bloqué : Élève Exemple')
            ->assertSee('Compte débloqué : Élève Exemple')
            ->assertSee('Joseph Admin')
            ->assertSee('Deuxième Admin');

        $this->actingAs($this->admin)->get(route('admin.activity.index', ['action' => 'user_blocked']))
            ->assertOk()
            ->assertSee('Compte bloqué : Élève Exemple')
            ->assertDontSee('Compte débloqué : Élève Exemple');

        $this->actingAs($this->admin)->get(route('admin.activity.index', ['user' => $other->id]))
            ->assertOk()
            ->assertSee('Compte débloqué : Élève Exemple')
            ->assertDontSee('Compte bloqué : Élève Exemple');

        $this->actingAs($this->admin)->get(route('admin.activity.index', ['from' => now()->addDay()->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Aucune activité pour ces filtres.');
    }
}
