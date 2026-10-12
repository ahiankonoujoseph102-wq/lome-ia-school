<?php

namespace Tests\Feature\Newsletters;

use App\Enums\AdminAction;
use App\Enums\NewsletterStatus;
use App\Enums\UserRole;
use App\Mail\NewsletterMail;
use App\Models\AdminActivity;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Écran « Envoi d'e-mails » : réservé aux administrateurs, brouillon, aperçu, test envoyé à soi seul,
 * confirmation avec le nombre de destinataires et de jours.
 */
class NewsletterAdminTest extends NewsletterTestCase
{
    public function test_only_administrators_can_open_the_screen(): void
    {
        $newsletter = $this->draft();
        $student = User::factory()->create();
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->get(route('admin.newsletters.index'))->assertRedirect(route('login'));

        foreach ([$student, $moderator] as $user) {
            $this->actingAs($user)->get(route('admin.newsletters.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.newsletters.preview', $newsletter))->assertForbidden();
            $this->actingAs($user)->post(route('admin.newsletters.test', $newsletter))->assertForbidden();
            $this->actingAs($user)->post(route('admin.newsletters.send', $newsletter), ['confirm_count' => 1])->assertForbidden();
        }

        $this->assertSame(NewsletterStatus::Draft, $newsletter->fresh()->status);
        Mail::assertNothingSent();

        $this->actingAs($this->admin)->get(route('admin.newsletters.index'))->assertOk()->assertSee('Envoi d\'e-mails');
        $this->actingAs($this->admin)->get(route('admin.index'))->assertOk()->assertSee(route('admin.newsletters.index'));
    }

    public function test_an_administrator_creates_a_draft_with_an_image_a_video_and_a_button(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('admin.newsletters.store'), [
            'subject' => 'Nouvelle formation',
            'body' => "Bonjour à tous.\nLigne deux.",
            'image' => UploadedFile::fake()->image('affiche.png', 1600, 1000),
            'video_url' => "Regardez : HTTPS://YOUTU.BE/dQw4w9WgXcQ\u{00A0}",
            'button_label' => 'Découvrir',
            'button_url' => '/accompagnement',
            'audience' => 'all',
        ])->assertRedirect();

        $newsletter = Newsletter::query()->sole();
        $this->assertSame(NewsletterStatus::Draft, $newsletter->status);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $newsletter->video_url);
        Storage::disk('public')->assertExists((string) $newsletter->image_path);
        $this->assertStringStartsWith('newsletters/', (string) $newsletter->image_path);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $newsletter->video()['url']);
        $this->assertSame(url('/accompagnement'), $newsletter->button()['url']);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::NewsletterUpdated->value)->exists());
    }

    public function test_the_form_refuses_an_unknown_video_link_a_lone_button_and_a_heavy_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('admin.newsletters.store'), [
            'subject' => 'Objet',
            'body' => 'Texte',
            'video_url' => 'https://vimeo.com/123',
            'button_label' => 'Découvrir',
            'image' => UploadedFile::fake()->create('lourd.jpg', 9000, 'image/jpeg'),
            'audience' => 'all',
        ])->assertSessionHasErrors(['video_url', 'button_url', 'image']);

        $this->actingAs($this->admin)->post(route('admin.newsletters.store'), [
            'subject' => 'Objet', 'body' => 'Texte', 'button_label' => 'Voir', 'button_url' => 'javascript:alert(1)', 'audience' => 'all',
        ])->assertSessionHasErrors('button_url');

        $this->assertSame(0, Newsletter::query()->count());
    }

    public function test_the_preview_shows_the_email_as_received_and_the_text_version(): void
    {
        $newsletter = $this->draft(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'button_label' => 'Voir les offres', 'button_url' => '/accompagnement']);

        $this->actingAs($this->admin)->get(route('admin.newsletters.show', $newsletter))->assertOk()
            ->assertSee('data-test="newsletter-preview"', false)
            ->assertSee('M\'envoyer un test', false);

        $this->actingAs($this->admin)->get(route('admin.newsletters.preview', $newsletter))->assertOk()
            ->assertSee('Bonjour Joseph,')
            ->assertSee('Elle explique les agents IA.')
            ->assertSee('Regarder la vidéo')
            ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
            ->assertSee('Voir les offres')
            ->assertSee('Se désabonner de ces e-mails')
            ->assertSee(config('site.contact.address'));

        $this->actingAs($this->admin)->get(route('admin.newsletters.preview', [$newsletter, 'version' => 'texte']))->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Regarder la vidéo : https://www.youtube.com/watch?v=dQw4w9WgXcQ', false)
            ->assertSee('Se désabonner de ces e-mails :', false);
    }

    public function test_the_test_email_goes_only_to_the_administrator(): void
    {
        $this->students(3);
        $newsletter = $this->draft();

        $this->actingAs($this->admin)->post(route('admin.newsletters.test', $newsletter))->assertRedirect()->assertSessionHas('status');

        Mail::assertSent(NewsletterMail::class, 1);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $mail): bool => $mail->hasTo('admin@example.com') && $mail->test && str_starts_with($mail->envelope()->subject, '[Test] '));
        $this->assertSame(NewsletterStatus::Draft, $newsletter->fresh()->status);
        $this->assertSame(0, $newsletter->recipients()->count());
    }

    public function test_the_confirmation_shows_the_exact_number_of_recipients_and_days(): void
    {
        config(['site.mail_quota.bulk_daily_limit' => 2]);
        $this->students(4); // + l'administrateur = 5 comptes confirmés
        User::factory()->unverified()->create();
        $newsletter = $this->draft();

        $this->actingAs($this->admin)->get(route('admin.newsletters.show', $newsletter))->assertOk()
            ->assertSeeInOrder(['data-test="newsletter-recipients"', '5'], false)
            ->assertSee('Cet e-mail sera envoyé à <strong>5 personnes</strong>', false)
            ->assertSee('environ 3 jours');
    }

    public function test_sending_requires_the_count_shown_in_the_confirmation(): void
    {
        $this->students(2);
        $newsletter = $this->draft();

        $this->actingAs($this->admin)->post(route('admin.newsletters.send', $newsletter), ['confirm_count' => 2])
            ->assertSessionHasErrors('newsletter');
        $this->assertSame(NewsletterStatus::Draft, $newsletter->fresh()->status);
        Mail::assertNothingSent();

        $this->actingAs($this->admin)->post(route('admin.newsletters.send', $newsletter), ['confirm_count' => 3])->assertRedirect();

        $this->assertSame(NewsletterStatus::Completed, $newsletter->fresh()->status);
        $this->assertSame(3, $newsletter->fresh()->recipients_count);
        Mail::assertSent(NewsletterMail::class, 3);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::NewsletterSent->value)->exists());

        // Double toucher : rien ne repart.
        $this->actingAs($this->admin)->post(route('admin.newsletters.send', $newsletter), ['confirm_count' => 3]);
        Mail::assertSent(NewsletterMail::class, 3);
    }

    public function test_a_launched_email_can_no_longer_be_edited_and_appears_in_the_history(): void
    {
        $this->students(1);
        $newsletter = $this->draft();
        $this->actingAs($this->admin)->post(route('admin.newsletters.send', $newsletter), ['confirm_count' => 2]);

        $this->actingAs($this->admin)->put(route('admin.newsletters.update', $newsletter), ['subject' => 'Changé', 'body' => 'x', 'audience' => 'all'])
            ->assertSessionHasErrors('newsletter');
        $this->assertSame('Nouvelle vidéo de Coach Joseph', $newsletter->fresh()->subject);

        $this->actingAs($this->admin)->get(route('admin.newsletters.index'))->assertOk()
            ->assertSee('Nouvelle vidéo de Coach Joseph')
            ->assertSee('Terminé')
            ->assertSeeInOrder(['Destinataires', '2', 'Envoyés', '2', 'En attente', '0', 'En échec', '0', 'Désabonnements', '0']);
    }
}
