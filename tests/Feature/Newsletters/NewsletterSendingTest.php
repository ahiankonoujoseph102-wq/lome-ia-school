<?php

namespace Tests\Feature\Newsletters;

use App\Enums\NewsletterAudience;
use App\Enums\NewsletterRecipientStatus;
use App\Enums\NewsletterStatus;
use App\Jobs\SendNewsletterEmail;
use App\Mail\EmailConfirmationMail;
use App\Mail\MailQuota;
use App\Mail\NewsletterMail;
use App\Models\ChatEmailAnnouncement;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\NewsletterRecipient;
use App\Models\User;
use App\Newsletters\NewsletterDispatcher;
use App\Newsletters\NewsletterManager;
use App\Newsletters\NewsletterQuota;
use App\Newsletters\NewsletterRecipients;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Envoi progressif : destinataires, plafond du jour, étalement sur plusieurs jours, priorité des e-mails
 * indispensables, nouveaux essais sans jamais deux e-mails pour la même personne, pause et annulation.
 */
class NewsletterSendingTest extends NewsletterTestCase
{
    private function manager(): NewsletterManager
    {
        return app(NewsletterManager::class);
    }

    public function test_recipients_are_confirmed_accounts_that_did_not_unsubscribe(): void
    {
        [$confirmed, $optIn] = $this->students(2);
        $optIn->forceFill(['wants_newsletter' => true])->save();
        $unconfirmed = User::factory()->unverified()->create();
        $blocked = User::factory()->create(['blocked_at' => now()]);
        $unsubscribed = User::factory()->create();
        $unsubscribed->forceFill(['bulk_email_unsubscribed_at' => now()])->save();

        $ids = NewsletterRecipients::query()->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$this->admin->id, $confirmed->id, $optIn->id])->sort()->values()->all(), $ids);
        $this->assertSame([$optIn->id], NewsletterRecipients::query(NewsletterAudience::OptIn)->pluck('id')->all());

        $newsletter = $this->draft();
        $this->manager()->start($newsletter, $this->admin);

        Mail::assertSent(NewsletterMail::class, 3);
        foreach ([$unconfirmed, $blocked, $unsubscribed] as $user) {
            Mail::assertNotSent(NewsletterMail::class, fn (NewsletterMail $mail): bool => $mail->hasTo($user->email));
        }
    }

    public function test_the_daily_cap_spreads_the_sending_over_several_days_without_any_action(): void
    {
        config(['site.mail_quota.bulk_daily_limit' => 3]);
        $this->students(6); // 7 destinataires avec l'administrateur
        $newsletter = $this->draft();

        $this->assertSame(3, NewsletterQuota::daysFor(7));
        $this->manager()->start($newsletter, $this->admin);
        Mail::assertSent(NewsletterMail::class, 3);

        // Le planificateur repasse chaque minute : rien de plus aujourd'hui.
        Artisan::call('app:send-newsletters');
        Artisan::call('app:send-newsletters');
        Mail::assertSent(NewsletterMail::class, 3);
        $this->assertSame(NewsletterStatus::Sending, $newsletter->fresh()->status);
        $this->assertSame(0, NewsletterQuota::availableToday());

        $this->travel(1)->days();
        Artisan::call('app:send-newsletters');
        Mail::assertSent(NewsletterMail::class, 6);

        $this->travel(1)->days();
        Artisan::call('app:send-newsletters');
        Mail::assertSent(NewsletterMail::class, 7);
        $this->assertSame(NewsletterStatus::Completed, $newsletter->fresh()->status);
        $this->assertSame(7, $newsletter->fresh()->stats()['sent']);
    }

    public function test_the_cap_can_never_take_the_reserve_of_essential_emails(): void
    {
        config(['site.mail_quota' => ['daily_limit' => 300, 'reserve' => 100, 'bulk_daily_limit' => 280]]);
        $this->assertSame(200, NewsletterQuota::dailyLimit());

        // Le site a déjà envoyé 150 e-mails aujourd'hui (codes, paiements…) : il ne reste que 50 pour les envois groupés.
        MailQuota::record(150);
        config(['site.mail_quota.bulk_daily_limit' => 200]);
        $this->assertSame(50, NewsletterQuota::availableToday());
    }

    public function test_essential_emails_keep_priority_once_the_bulk_cap_is_reached(): void
    {
        config(['site.mail_quota.bulk_daily_limit' => 2]);
        $this->students(4);
        $this->manager()->start($this->draft(), $this->admin);
        Mail::assertSent(NewsletterMail::class, 2);
        $this->assertSame(0, NewsletterQuota::availableToday());

        // Un code de confirmation part toujours : il n'est jamais retenu par les envois groupés.
        $newcomer = User::factory()->unverified()->create();
        $this->actingAs($newcomer)->post(route('email-confirmation.resend'));
        Mail::assertSent(EmailConfirmationMail::class, fn (EmailConfirmationMail $mail): bool => $mail->hasTo($newcomer->email));

        // Compteur réel du site (sans Mail::fake, l'écouteur CountSentEmail compte chaque e-mail parti) :
        // 2 e-mails groupés + 1 code. La réserve reste entière pour le reste de la journée.
        MailQuota::record(3);
        $this->assertGreaterThanOrEqual(MailQuota::reserve(), MailQuota::dailyLimit() - MailQuota::sentToday());
        $this->assertSame(300 - 100 - 3, MailQuota::availableForAnnouncements());
    }

    public function test_group_announcements_waiting_in_the_queue_go_first(): void
    {
        config(['site.mail_quota' => ['daily_limit' => 300, 'reserve' => 100, 'bulk_daily_limit' => 200]]);
        $group = ChatGroup::forFormation(Formation::factory()->create());
        ChatEmailAnnouncement::query()->create(['chat_group_id' => $group->id, 'user_id' => $this->admin->id, 'recipients' => 180]);
        MailQuota::record(10);

        $this->assertSame(10, NewsletterQuota::availableToday(), '300 − 100 de réserve − 10 partis − 180 annonces en file');
    }

    public function test_an_email_is_never_sent_twice_to_the_same_person(): void
    {
        $this->students(2);
        $newsletter = $this->draft();
        $this->manager()->start($newsletter, $this->admin);
        Mail::assertSent(NewsletterMail::class, 3);

        // Même travail rejoué (file relancée), répartiteur relancé : rien ne repart.
        foreach (NewsletterRecipient::query()->pluck('id') as $id) {
            (new SendNewsletterEmail((int) $id))->handle();
        }
        app(NewsletterDispatcher::class)->run();
        Mail::assertSent(NewsletterMail::class, 3);

        // Un nouveau lancement du même envoi est refusé, et une personne n'a qu'une ligne par envoi.
        $this->assertNull($this->manager()->start($newsletter->fresh(), $this->admin));
        $this->assertSame(3, $newsletter->recipients()->count());
        Mail::assertSent(NewsletterMail::class, 3);
    }

    public function test_an_interrupted_sending_is_never_sent_again(): void
    {
        $this->students(1);
        $newsletter = $this->draft();
        Mail::shouldReceive('to')->never();
        config(['site.mail_quota.bulk_daily_limit' => 0]);
        $this->manager()->start($newsletter, $this->admin);

        // Un e-mail est resté « en cours » (serveur arrêté pendant l'envoi) : classé en échec, jamais renvoyé.
        $stuck = $newsletter->recipients()->first();
        $stuck->forceFill(['status' => NewsletterRecipientStatus::Sending, 'updated_at' => now()->subHour()])->saveQuietly();
        // Un autre est resté « en file » (travail perdu) : remis en attente.
        $lost = $newsletter->recipients()->whereKeyNot($stuck->id)->first();
        $lost->forceFill(['status' => NewsletterRecipientStatus::Queued, 'queued_at' => now()->subHour()])->saveQuietly();

        app(NewsletterDispatcher::class)->run();

        $this->assertSame(NewsletterRecipientStatus::Failed, $stuck->fresh()->status);
        $this->assertSame(NewsletterRecipientStatus::Pending, $lost->fresh()->status);
    }

    public function test_a_failed_email_is_retried_later_then_marked_as_failed(): void
    {
        $newsletter = $this->draft();
        $calls = 0;
        Mail::shouldReceive('to')->andReturnUsing(function () use (&$calls) {
            $calls++;

            throw new RuntimeException('SMTP indisponible');
        });

        $this->manager()->start($newsletter, $this->admin);
        $recipient = $newsletter->recipients()->sole();
        $this->assertSame(1, $calls);
        $this->assertSame(NewsletterRecipientStatus::Pending, $recipient->fresh()->status);
        $this->assertSame('SMTP indisponible', $recipient->fresh()->last_error);

        // Pas de nouvel essai avant 15 minutes.
        app(NewsletterDispatcher::class)->run();
        $this->assertSame(1, $calls);

        $this->travel(16)->minutes();
        app(NewsletterDispatcher::class)->run();
        $this->assertSame(2, $calls);

        $this->travel(121)->minutes();
        app(NewsletterDispatcher::class)->run();
        $this->assertSame(3, $calls);
        $this->assertSame(NewsletterRecipientStatus::Failed, $recipient->fresh()->status);

        $this->travel(1)->days();
        app(NewsletterDispatcher::class)->run();
        $this->assertSame(3, $calls, 'Après le dernier essai, plus rien n\'est tenté.');
        $this->assertSame(NewsletterStatus::Completed, $newsletter->fresh()->status);
        $this->assertSame(1, $newsletter->fresh()->stats()['failed']);
    }

    public function test_a_successful_retry_sends_a_single_email(): void
    {
        $newsletter = $this->draft();
        $calls = 0;
        Mail::shouldReceive('to')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 1) {
                throw new RuntimeException('SMTP indisponible');
            }

            return new class
            {
                public function send(): void {}
            };
        });

        $this->manager()->start($newsletter, $this->admin);
        $this->travel(16)->minutes();
        app(NewsletterDispatcher::class)->run();
        app(NewsletterDispatcher::class)->run();
        $this->travel(1)->days();
        app(NewsletterDispatcher::class)->run();

        $this->assertSame(2, $calls);
        $this->assertSame(NewsletterRecipientStatus::Sent, $newsletter->recipients()->sole()->status);
    }

    public function test_pause_resume_and_cancel(): void
    {
        config(['site.mail_quota.bulk_daily_limit' => 1]);
        $this->students(3);
        $newsletter = $this->draft();
        $this->manager()->start($newsletter, $this->admin);
        Mail::assertSent(NewsletterMail::class, 1);

        $this->actingAs($this->admin)->post(route('admin.newsletters.pause', $newsletter))->assertSessionHas('status');
        $this->assertSame(NewsletterStatus::Paused, $newsletter->fresh()->status);
        $this->travel(1)->days();
        Artisan::call('app:send-newsletters');
        Mail::assertSent(NewsletterMail::class, 1);

        $this->actingAs($this->admin)->post(route('admin.newsletters.resume', $newsletter));
        $this->assertSame(NewsletterStatus::Sending, $newsletter->fresh()->status);
        Mail::assertSent(NewsletterMail::class, 2);

        $this->actingAs($this->admin)->post(route('admin.newsletters.cancel', $newsletter));
        $this->assertSame(NewsletterStatus::Cancelled, $newsletter->fresh()->status);
        $this->travel(1)->days();
        Artisan::call('app:send-newsletters');
        Mail::assertSent(NewsletterMail::class, 2);
        $this->assertSame(2, $newsletter->fresh()->stats()['cancelled']);
    }

    public function test_a_person_who_unsubscribes_after_the_launch_receives_nothing(): void
    {
        config(['site.mail_quota.bulk_daily_limit' => 1]);
        [$student] = $this->students(1);
        $newsletter = $this->draft();
        $this->manager()->start($newsletter, $this->admin); // l'administrateur reçoit le premier

        $student->forceFill(['bulk_email_unsubscribed_at' => now()])->save();
        $this->travel(1)->days();
        Artisan::call('app:send-newsletters');

        Mail::assertNotSent(NewsletterMail::class, fn (NewsletterMail $mail): bool => $mail->hasTo($student->email));
        $this->assertSame(NewsletterRecipientStatus::Skipped, $newsletter->recipients()->where('user_id', $student->id)->sole()->status);
    }

    public function test_the_settings_refuse_a_cap_that_would_take_the_reserve(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'emails'), ['daily_limit' => 300, 'reserve' => 100, 'bulk_daily_limit' => 201])
            ->assertSessionHasErrors('bulk_daily_limit');

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'emails'), ['daily_limit' => 300, 'reserve' => 100, 'bulk_daily_limit' => 150])
            ->assertSessionHasNoErrors();
        $this->assertSame(150, NewsletterQuota::dailyLimit());

        // Page ouverte avant la mise à jour (sans le champ) : le plafond enregistré est gardé.
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'emails'), ['daily_limit' => 300, 'reserve' => 120])
            ->assertSessionHasNoErrors();
        $this->assertSame(150, NewsletterQuota::dailyLimit());
    }

    public function test_the_default_cap_leaves_room_for_the_rest_of_the_site(): void
    {
        $defaults = (require config_path('site.php'))['mail_quota'];
        config(['site.mail_quota' => $defaults]);

        $this->assertSame(200, $defaults['bulk_daily_limit']);
        $this->assertLessThanOrEqual($defaults['daily_limit'] - $defaults['reserve'], $defaults['bulk_daily_limit']);
        $this->assertSame(200, NewsletterQuota::dailyLimit());
    }
}
