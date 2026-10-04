<?php

namespace Tests\Feature\Chat;

use App\Chat\ChatBadges;
use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Mail\SecurityAlertMail;
use App\Models\AdminActivity;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\Mail;

/**
 * Étiquettes dans les groupes : « Formateur principal » (doré) pour le compte principal, « Administrateur »
 * (violet) pour les autres administrateurs, « Équipe » pour les modérateurs. Décidées par le rôle uniquement,
 * jamais par le nom ; libellés et compte principal réglés dans l'admin (Équipe).
 */
class ChatBadgesTest extends ChatTestCase
{
    private function joseph(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Joseph Ahiankonou', 'email' => 'academyjoseph71@gmail.com']);
    }

    public function test_the_main_account_is_gold_other_admins_violet_and_moderators_team(): void
    {
        $other = $this->admin();
        $joseph = $this->joseph();
        $moderator = $this->moderator();
        $student = $this->student(['name' => 'Kossi Agbeko']);

        $this->message($joseph, 'Bienvenue dans le groupe');
        $this->message($other, 'Je suis là aussi');
        $this->message($moderator, 'Rappel des règles');
        $this->message($student, 'Merci !');

        // Par défaut : le compte du responsable du site (academyjoseph71@gmail.com), même s'il n'est pas le plus ancien.
        $this->assertSame(ChatBadges::PRINCIPAL, $joseph->chatBadgeKind());
        $this->assertSame(ChatBadges::ADMIN, $other->chatBadgeKind());
        $this->assertSame(ChatBadges::TEAM, $moderator->chatBadgeKind());
        $this->assertNull($student->chatBadgeKind());

        $page = $this->actingAs($student)->get($this->url('chat.show'))->assertOk();
        $page->assertSeeInOrder(['Joseph Ahiankonou', 'chat-badge chat-badge-principal', 'Formateur principal'], false);
        $page->assertSeeInOrder(['Joseph Formateur', 'chat-badge chat-badge-admin', 'Administrateur'], false);
        $page->assertSeeInOrder(['Esi Équipe', 'chat-badge chat-badge-team', 'Équipe'], false);
        $this->assertSame(1, substr_count($page->getContent(), 'chat-badge-principal'));
    }

    public function test_without_the_owner_account_the_oldest_admin_is_the_main_trainer(): void
    {
        $first = $this->admin();
        $second = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertSame($first->id, ChatBadges::principalId());
        $this->assertSame('Administrateur', $second->chatBadge());
    }

    public function test_a_student_never_gets_a_badge_even_with_a_title_in_their_name(): void
    {
        $this->joseph();
        $student = $this->student(['name' => 'Kodjo Formateur principal']);
        $this->message($student, 'Bonjour');

        $this->assertNull($student->chatBadge());
        $page = $this->actingAs($student)->get($this->url('chat.show'));
        $this->assertStringNotContainsString('chat-badge-principal', $page->getContent());
    }

    public function test_names_cannot_imitate_team_titles_at_registration_or_in_the_profile(): void
    {
        foreach (['Kossi Formateur principal', 'ADMINISTRATEUR Ama', 'Ékué (Modérateur)', 'Équipe Lomé', 'Kofi admin'] as $name) {
            $this->assertTrue(ChatBadges::imitates($name), $name);
        }

        foreach (['Kossi Agbeko', 'Adminata Diallo', 'Joseph Ahiankonou', 'Afi Formation'] as $name) {
            $this->assertFalse(ChatBadges::imitates($name), $name);
        }

        $this->post(route('register.store'), [
            'name' => 'Mensah Administrateur',
            'email' => 'mensah@example.com',
            'phone' => '90 12 34 56',
            'phone_country' => '+228',
            'password' => 'motdepasse123',
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('users', ['email' => 'mensah@example.com']);

        $student = $this->student(['phone' => '+22890123457']);
        $this->actingAs($student)->put(route('student.profile.update'), [
            'name' => 'Formateur principal',
            'email' => $student->email,
            'phone' => '90 12 34 57',
            'phone_country' => '+228',
        ])->assertSessionHasErrors('name');
        $this->assertNotSame('Formateur principal', $student->fresh()->name);
    }

    public function test_the_admin_chooses_the_main_account_and_the_labels_with_the_password(): void
    {
        Mail::fake();
        $joseph = $this->joseph();
        $other = $this->admin();

        $this->actingAs($joseph)->get(route('admin.moderators.index'))
            ->assertOk()
            ->assertSee('Étiquettes dans les groupes')
            ->assertSee('value="Formateur principal"', false)
            ->assertSee('value="Administrateur"', false);

        // Mot de passe obligatoire.
        $this->actingAs($joseph)->put(route('admin.moderators.badges'), [
            'principal_user_id' => $other->id, 'principal_label' => 'Coach principal', 'admin_label' => 'Admin du site',
        ])->assertSessionHasErrorsIn('badges', 'current_password');
        $this->assertSame($joseph->id, ChatBadges::principalId());

        $this->actingAs($joseph)->put(route('admin.moderators.badges'), [
            'principal_user_id' => $joseph->id, 'principal_label' => 'Coach principal', 'admin_label' => 'Admin du site', 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Coach principal', $joseph->fresh()->chatBadge());
        $this->assertSame('Admin du site', $other->fresh()->chatBadge());
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->exists());
        // Même compte principal : pas de changement de rôle.
        $this->assertFalse(AdminActivity::query()->where('action', AdminAction::RoleChanged)->exists());

        // Changer de compte principal : changement de rôle, avec e-mail d'alerte.
        $this->actingAs($joseph)->put(route('admin.moderators.badges'), [
            'principal_user_id' => $other->id, 'principal_label' => 'Coach principal', 'admin_label' => 'Admin du site', 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->assertSame(ChatBadges::PRINCIPAL, $other->fresh()->chatBadgeKind());
        $this->assertSame(ChatBadges::ADMIN, $joseph->fresh()->chatBadgeKind());
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::RoleChanged)->exists());
        Mail::assertQueued(SecurityAlertMail::class);
    }

    public function test_the_main_account_must_be_an_admin_and_labels_must_differ(): void
    {
        $joseph = $this->joseph();
        $moderator = $this->moderator();

        $this->actingAs($joseph)->put(route('admin.moderators.badges'), [
            'principal_user_id' => $moderator->id, 'principal_label' => 'Formateur principal', 'admin_label' => 'Administrateur', 'current_password' => 'password',
        ])->assertSessionHasErrorsIn('badges', 'principal_user_id');

        $this->actingAs($joseph)->put(route('admin.moderators.badges'), [
            'principal_user_id' => $joseph->id, 'principal_label' => 'Admin', 'admin_label' => 'admin', 'current_password' => 'password',
        ])->assertSessionHasErrorsIn('badges', 'admin_label');

        // Compte choisi qui n'est plus administrateur : retour à la règle par défaut.
        SiteSettings::save(['site.chat_badges' => ['principal_user_id' => $moderator->id, 'principal' => 'Formateur principal', 'admin' => 'Administrateur']]);
        $this->assertSame($joseph->id, ChatBadges::principalId());
    }

    public function test_moderators_and_students_cannot_change_the_badges(): void
    {
        $joseph = $this->joseph();

        foreach ([$this->moderator(), $this->student()] as $user) {
            $this->actingAs($user)->put(route('admin.moderators.badges'), [
                'principal_user_id' => $user->id, 'principal_label' => 'Moi', 'admin_label' => 'Autre', 'current_password' => 'password',
            ])->assertForbidden();
        }

        $this->assertSame($joseph->id, ChatBadges::principalId());
    }
}
