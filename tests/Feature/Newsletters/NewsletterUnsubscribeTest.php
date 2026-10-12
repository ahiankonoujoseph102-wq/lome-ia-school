<?php

namespace Tests\Feature\Newsletters;

use App\Enums\NewsletterAudience;
use App\Mail\EmailConfirmationMail;
use App\Mail\NewsletterMail;
use App\Models\User;
use App\Newsletters\BulkEmailSubscription;
use App\Newsletters\NewsletterManager;
use App\Newsletters\NewsletterRecipients;
use Illuminate\Support\Facades\Mail;

/**
 * Désabonnement en un clic, sans connexion (lien signé et en-tête « List-Unsubscribe » de Gmail),
 * limité aux envois groupés, et réabonnement depuis le compte.
 */
class NewsletterUnsubscribeTest extends NewsletterTestCase
{
    public function test_each_email_has_an_unsubscribe_link_and_the_gmail_headers(): void
    {
        [$student] = $this->students(1);
        $newsletter = $this->draft();
        app(NewsletterManager::class)->start($newsletter, $this->admin);

        Mail::assertSent(NewsletterMail::class, function (NewsletterMail $mail) use ($student, $newsletter): bool {
            if (! $mail->hasTo($student->email)) {
                return false;
            }

            $url = BulkEmailSubscription::unsubscribeUrl($student, $newsletter);
            $headers = $mail->headers()->text;
            $html = $mail->render();
            $text = view('emails.newsletter-text', $mail->details() + ['newsletter' => $newsletter])->render();

            return $mail->unsubscribeUrl === $url
                && $headers['List-Unsubscribe'] === '<'.$url.'>'
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
                && str_contains($html, e($url))
                && str_contains($html, 'Se désabonner de ces e-mails')
                && str_contains($text, 'Se désabonner de ces e-mails : '.$url)
                && str_contains($text, 'Bonjour '.$student->first_name.',');
        });
    }

    public function test_the_link_unsubscribes_in_one_click_without_logging_in(): void
    {
        [$student] = $this->students(1);
        $newsletter = $this->draft();
        app(NewsletterManager::class)->start($newsletter, $this->admin);

        $this->get(BulkEmailSubscription::unsubscribeUrl($student, $newsletter))->assertOk()
            ->assertSee('Vous êtes désabonné(e)')
            ->assertSee('Vous continuerez à recevoir les e-mails liés à votre compte et à vos achats');

        $this->assertTrue($student->fresh()->hasUnsubscribedFromBulkEmails());
        $this->assertGuest();
        $this->assertSame(1, $newsletter->fresh()->stats()['unsubscribed']);
        $this->assertFalse(NewsletterRecipients::query()->whereKey($student->id)->exists());
    }

    public function test_a_tampered_link_is_refused(): void
    {
        [$student, $other] = $this->students(2);
        $url = BulkEmailSubscription::unsubscribeUrl($student);

        $this->get(str_replace('/desabonnement/'.$student->id, '/desabonnement/'.$other->id, $url))->assertForbidden();
        $this->get(route('newsletter.unsubscribe', ['user' => $student->id]))->assertForbidden();

        $this->assertFalse($student->fresh()->hasUnsubscribedFromBulkEmails());
        $this->assertFalse($other->fresh()->hasUnsubscribedFromBulkEmails());
    }

    public function test_gmail_one_click_unsubscribe_works_without_a_csrf_token(): void
    {
        [$student] = $this->students(1);

        $this->post(BulkEmailSubscription::unsubscribeUrl($student), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertTrue($student->fresh()->hasUnsubscribedFromBulkEmails());
    }

    public function test_the_person_can_resubscribe_from_the_page_or_from_the_account(): void
    {
        [$student] = $this->students(1);
        $this->get(BulkEmailSubscription::unsubscribeUrl($student))->assertSee('me réabonner');

        $this->post(BulkEmailSubscription::resubscribeUrl($student))->assertOk()->assertSee('Vous êtes de nouveau abonné(e)');
        $this->assertFalse($student->fresh()->hasUnsubscribedFromBulkEmails());

        // Depuis Mon espace > Notifications : décocher puis recocher.
        $this->actingAs($student)->get(route('student.notifications'))->assertOk()->assertSee('data-test="bulk-emails"', false);
        $this->actingAs($student)->put(route('student.notifications.update'), ['bulk_emails_sent' => '1'])->assertSessionHas('status');
        $this->assertTrue($student->fresh()->hasUnsubscribedFromBulkEmails());

        $this->actingAs($student)->put(route('student.notifications.update'), ['bulk_emails_sent' => '1', 'bulk_emails' => '1']);
        $this->assertFalse($student->fresh()->hasUnsubscribedFromBulkEmails());

        // Formulaire d'avant la mise à jour (sans le champ) : rien ne change.
        $student->forceFill(['bulk_email_unsubscribed_at' => now()])->save();
        $this->actingAs($student)->put(route('student.notifications.update'), ['chat_digest_enabled' => '1']);
        $this->assertTrue($student->fresh()->hasUnsubscribedFromBulkEmails());
    }

    public function test_unsubscribing_only_stops_bulk_emails(): void
    {
        $student = User::factory()->unverified()->create();
        $student->forceFill(['bulk_email_unsubscribed_at' => now()])->save();

        // Le code de confirmation (e-mail indispensable) part toujours.
        $this->actingAs($student)->post(route('email-confirmation.resend'));
        Mail::assertSent(EmailConfirmationMail::class, fn ($mail): bool => $mail->hasTo($student->email));

        $student->forceFill(['email_verified_at' => now()])->save();
        $this->assertFalse(NewsletterRecipients::query()->whereKey($student->id)->exists());
        $this->assertFalse(NewsletterRecipients::accepts($student->fresh(), NewsletterAudience::All));
    }

    public function test_privacy_policy_mentions_the_bulk_emails_and_unsubscribing(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('data-test="privacy-bulk-emails"', false)
            ->assertSee('Se désabonner de ces e-mails');
    }
}
