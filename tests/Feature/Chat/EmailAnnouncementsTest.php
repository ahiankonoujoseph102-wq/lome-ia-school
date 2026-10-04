<?php

namespace Tests\Feature\Chat;

use App\Chat\EmailAnnouncements;
use App\Enums\AdminAction;
use App\Enums\ChatRemovalReason;
use App\Jobs\SendChatAnnouncementEmail;
use App\Mail\ChatAnnouncementMail;
use App\Mail\MailQuota;
use App\Mail\WelcomeMail;
use App\Models\AdminActivity;
use App\Models\ChatEmailAnnouncement;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * Annonce importante envoyée aussi par e-mail : réservée aux administrateurs, comptée dans le quota du jour,
 * jamais au point d'entamer la réserve des codes de confirmation et des paiements.
 */
class EmailAnnouncementsTest extends ChatTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['site.mail_quota' => ['daily_limit' => 300, 'reserve' => 100]]);
        $this->admin = $this->admin();
        $this->get('/'); // le groupe existe (et l'admin en est membre en ouvrant la liste)
        $this->actingAs($this->admin)->get(route('chat.index'));
    }

    /**
     * @return list<User>
     */
    private function confirmedStudents(int $count): array
    {
        return array_map(fn (int $i): User => $this->student(['name' => 'Élève '.$i]), range(1, $count));
    }

    private function announce(string $body = 'Session en direct ce soir à 20 h.', bool $email = true): TestResponse
    {
        return $this->actingAs($this->admin)->postJson($this->url('chat.messages.store'), [
            'type' => 'text',
            'body' => $body,
            'email_members' => $email ? '1' : '0',
        ]);
    }

    public function test_only_administrators_see_the_checkbox(): void
    {
        $student = $this->student();
        $moderator = $this->moderator();

        $this->actingAs($this->admin)->get($this->url('chat.show'))->assertOk()
            ->assertSee('data-test="email-members"', false)
            ->assertSee('Envoyer aussi par e-mail aux membres');

        $this->actingAs($student)->get($this->url('chat.show'))->assertOk()
            ->assertDontSee('data-test="email-members"', false)
            ->assertDontSee('Envoyer aussi par e-mail');

        $this->actingAs($moderator)->get($this->url('chat.show'))->assertOk()
            ->assertDontSee('data-test="email-members"', false);

        $this->actingAs($student)->getJson($this->url('chat.announcement-email'))->assertForbidden();
    }

    public function test_the_preview_counts_only_members_with_a_confirmed_address(): void
    {
        $this->confirmedStudents(3);
        $this->student(['email_verified_at' => null]);
        $this->student(['blocked_at' => now()]);
        $removed = $this->student();
        ChatGroupMember::query()->where('user_id', $removed->id)->update(['removed_at' => now(), 'removal_reason' => ChatRemovalReason::Excluded]);

        $this->actingAs($this->admin)->getJson($this->url('chat.announcement-email'))
            ->assertOk()
            ->assertJson(['allowed' => true, 'recipients' => 3, 'text' => 'Cet e-mail sera envoyé à 3 membres.']);
    }

    public function test_an_announcement_is_published_and_emailed_to_confirmed_members(): void
    {
        Mail::fake();
        [$first] = $this->confirmedStudents(2);
        $unconfirmed = $this->student(['email_verified_at' => null]);

        $this->announce()
            ->assertCreated()
            ->assertJsonPath('email.status', 'queued')
            ->assertJsonPath('email.recipients', 2)
            ->assertJsonPath('email.text', 'Annonce publiée. E-mail en cours d\'envoi à 2 membres.');

        $this->assertSame('Session en direct ce soir à 20 h.', ChatMessage::query()->latest('id')->value('body'));
        Mail::assertSent(ChatAnnouncementMail::class, 2);
        Mail::assertNotSent(ChatAnnouncementMail::class, fn (ChatAnnouncementMail $mail): bool => $mail->hasTo($unconfirmed->email));
        Mail::assertNotSent(ChatAnnouncementMail::class, fn (ChatAnnouncementMail $mail): bool => $mail->hasTo($this->admin->email));

        $mail = Mail::sent(ChatAnnouncementMail::class, fn (ChatAnnouncementMail $mail): bool => $mail->hasTo($first->email))->first();
        $mail->assertHasSubject('Annonce importante : Automatisation avec l\'IA');
        $mail->assertSeeInHtml('Session en direct ce soir à 20 h.');
        $mail->assertSeeInHtml('Automatisation avec l\'IA');
        $mail->assertSeeInHtml(route('chat.show', $this->formation));
        $mail->assertSeeInHtml('Ouvrir le groupe');

        $announcement = ChatEmailAnnouncement::query()->sole();
        $this->assertSame([2, 2, 0, 0], [$announcement->recipients, $announcement->sent, $announcement->failed, $announcement->skipped]);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::ChatEmailAnnouncement)->exists());
    }

    public function test_the_email_is_never_sent_without_the_box_or_by_a_student(): void
    {
        Mail::fake();
        $student = $this->student();
        $this->confirmedStudents(2);

        $this->announce(email: false)->assertCreated()->assertJsonPath('email', null);

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Coucou', 'email_members' => '1'])
            ->assertCreated()
            ->assertJsonPath('email', null);

        $moderator = $this->moderator();
        $this->actingAs($moderator)->get(route('chat.index'));
        $this->actingAs($moderator)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Équipe', 'email_members' => '1'])
            ->assertCreated()
            ->assertJsonPath('email', null);

        Mail::assertNothingSent();
        $this->assertSame(0, ChatEmailAnnouncement::query()->count());
    }

    public function test_the_announcement_is_refused_when_the_reserve_would_be_touched(): void
    {
        Mail::fake();
        $this->confirmedStudents(3);
        // 198 e-mails déjà partis : 300 - 100 - 198 = 2 restants, il en faudrait 3.
        MailQuota::record(198);

        $this->actingAs($this->admin)->getJson($this->url('chat.announcement-email'))
            ->assertOk()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('recipients', 3);

        $response = $this->announce()->assertCreated()->assertJsonPath('email.status', 'refused');

        $this->assertStringContainsString('il faudrait 3 e-mails, mais il n\'en reste que 2 aujourd\'hui', $response->json('email.text'));
        $this->assertStringContainsString('réserve de 100 e-mails', $response->json('email.text'));
        $this->assertStringContainsString('(déjà envoyés aujourd\'hui : 198 sur 300)', $response->json('email.text'));
        $this->assertStringContainsString('Le message est publié dans le groupe, sans e-mail.', $response->json('email.text'));

        // L'annonce est quand même publiée dans le groupe, sans aucun e-mail.
        $this->assertSame('Session en direct ce soir à 20 h.', ChatMessage::query()->latest('id')->value('body'));
        Mail::assertNothingSent();
        $this->assertSame(0, ChatEmailAnnouncement::query()->count());
    }

    public function test_the_announcement_may_use_everything_up_to_the_reserve(): void
    {
        Mail::fake();
        $this->confirmedStudents(3);
        MailQuota::record(197);

        $this->announce()->assertJsonPath('email.status', 'queued');
        Mail::assertSent(ChatAnnouncementMail::class, 3);
    }

    public function test_announcements_still_waiting_in_the_queue_count_against_the_quota(): void
    {
        $this->confirmedStudents(3);
        ChatEmailAnnouncement::query()->create(['chat_group_id' => $this->group()->id, 'recipients' => 199, 'sent' => 0, 'created_at' => now()->subHour()]);

        $this->assertSame(1, MailQuota::availableForAnnouncements());
        $this->actingAs($this->admin)->getJson($this->url('chat.announcement-email'))->assertJsonPath('allowed', false);
    }

    public function test_thresholds_follow_the_admin_settings(): void
    {
        Mail::fake();
        $this->confirmedStudents(3);
        MailQuota::record(198);

        // Offre plus large réglée dans l'admin : l'envoi devient possible.
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'emails'), ['daily_limit' => 1000, 'reserve' => 100])
            ->assertRedirect(route('admin.settings.edit', 'emails'));

        $this->assertSame(1000, MailQuota::dailyLimit());
        $this->announce()->assertJsonPath('email.status', 'queued');
    }

    public function test_the_settings_reject_a_reserve_larger_than_the_quota(): void
    {
        $this->actingAs($this->admin)->from(route('admin.settings.edit', 'emails'))
            ->put(route('admin.settings.update', 'emails'), ['daily_limit' => 300, 'reserve' => 300])
            ->assertSessionHasErrors(['reserve' => 'La réserve doit être plus petite que le quota quotidien.']);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'emails'))
            ->assertOk()
            ->assertSee('data-test="mail-quota-today"', false)
            ->assertSee('e-mails partis');

        $this->actingAs($this->student())->get(route('admin.settings.edit', 'emails'))->assertForbidden();
    }

    public function test_only_one_email_announcement_every_ten_minutes_per_group(): void
    {
        Mail::fake();
        $this->confirmedStudents(2);

        $this->announce('Première annonce')->assertJsonPath('email.status', 'queued');

        $second = $this->announce('Deuxième annonce')->assertCreated()->assertJsonPath('email.status', 'refused');
        $this->assertStringContainsString('une seule annonce par e-mail toutes les 10 minutes', $second->json('email.text'));
        $this->assertSame('Deuxième annonce', ChatMessage::query()->latest('id')->value('body'), 'Le message est publié quand même.');
        Mail::assertSent(ChatAnnouncementMail::class, 2);

        $this->travel(11)->minutes();
        $this->announce('Troisième annonce')->assertJsonPath('email.status', 'queued');
        Mail::assertSent(ChatAnnouncementMail::class, 4);
    }

    public function test_an_announcement_without_text_is_not_emailed(): void
    {
        Mail::fake();
        $this->confirmedStudents(2);
        $message = $this->message($this->admin, null, ['type' => 'image']);

        $result = app(EmailAnnouncements::class)->send($message, $this->admin);

        $this->assertSame('refused', $result['status']);
        Mail::assertNothingSent();
    }

    public function test_every_email_sent_by_the_site_is_counted_for_the_day(): void
    {
        $this->assertSame(0, MailQuota::sentToday());

        // Tous les e-mails comptent, pas seulement les annonces (codes, paiements, bienvenue…).
        Mail::to('a@exemple.com')->send(new WelcomeMail($this->student()));
        Mail::to('b@exemple.com')->send(new ChatAnnouncementMail('Ama', 'Groupe', 'Joseph', 'Texte', 'https://exemple.com'));

        $this->assertSame(2, MailQuota::sentToday());

        // Nouvelle journée de Lomé : le compteur repart de zéro.
        $this->travel(1)->days();
        $this->assertSame(0, MailQuota::sentToday());
    }

    public function test_the_admin_sees_how_many_emails_left_after_a_partial_failure(): void
    {
        [$first, $second, $third] = $this->confirmedStudents(3);

        // Le fournisseur refuse un des trois e-mails (trois essais, puis échec).
        Mail::shouldReceive('to')->andReturnUsing(function ($user) use ($second) {
            if ($user->is($second)) {
                throw new RuntimeException('SMTP indisponible');
            }

            return new class
            {
                public function send(): void {}
            };
        });

        $message = $this->message($this->admin, 'Annonce importante');
        $announcement = ChatEmailAnnouncement::query()->create(['chat_group_id' => $this->group()->id, 'chat_message_id' => $message->id, 'user_id' => $this->admin->id, 'recipients' => 3]);

        foreach ([$first, $second, $third] as $user) {
            $job = new SendChatAnnouncementEmail($announcement->id, $user->id);

            try {
                $job->handle();
            } catch (RuntimeException $exception) {
                $job->failed($exception);
            }
        }

        $announcement->refresh();
        $this->assertSame([2, 1], [$announcement->sent, $announcement->failed]);

        $this->actingAs($this->admin)->get(route('admin.chat.index'))
            ->assertOk()
            ->assertSee('data-test="email-announcements"', false)
            ->assertSeeInOrder(['Annonce importante', '2</span> / 3 partis', '1</span> en échec'], false);
    }

    public function test_a_message_deleted_before_sending_is_not_emailed(): void
    {
        Mail::fake();
        [$student] = $this->confirmedStudents(1);
        $message = $this->message($this->admin, 'Erreur, à supprimer');
        $announcement = ChatEmailAnnouncement::query()->create(['chat_group_id' => $this->group()->id, 'chat_message_id' => $message->id, 'user_id' => $this->admin->id, 'recipients' => 1]);
        $message->wipe($this->admin);

        (new SendChatAnnouncementEmail($announcement->id, $student->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame(1, $announcement->refresh()->skipped);
    }
}
