<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Mail\QuoteRequestMail;
use App\Models\AdminActivity;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Page « Entreprises et réalisations sur mesure » (novembre 2026) : « Nous le faisons pour vous », formulaire
 * « Demander un devis » (sans compte, e-mail à l'admin, liste dans l'admin), toujours aucun bouton WhatsApp.
 */
class QuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Afi Mensah',
            'organization' => 'Cabinet Exemple',
            'email' => 'afi@example.com',
            'phone' => '+228 90 12 34 56',
            'service' => 'site-vitrine',
            'message' => 'Un site pour présenter mon cabinet et prendre des rendez-vous.',
            ...$overrides,
        ];
    }

    public function test_the_page_shows_the_custom_work_and_the_quote_form_without_whatsapp(): void
    {
        $html = (string) $this->get(route('business'))->assertOk()
            ->assertSee('Entreprises et réalisations sur mesure')
            ->assertSee('data-test="business-custom-work"', false)
            ->assertSee('Nous le faisons pour vous')
            ->assertSee('Site vitrine')
            ->assertSee('Site professionnel')
            ->assertSee('Agent IA')
            ->assertSee('Automatisation')
            ->assertSee('id="devis"', false)
            ->assertSee('Demander un devis')
            ->getContent();

        // Décision de Joseph : aucun bouton WhatsApp sur la page (l'icône de l'en-tête reste).
        $main = substr($html, (int) strpos($html, 'data-test="business-page"'));
        $this->assertStringNotContainsString('wa.me', substr($main, 0, (int) strpos($main, '</main>') ?: strlen($main)));
    }

    public function test_a_visitor_sends_a_quote_request_and_the_admin_receives_it(): void
    {
        Mail::fake();

        $this->post(route('business.quote'), $this->form())
            ->assertRedirect(route('business').'#devis')
            ->assertSessionHas('quote_sent');

        $quote = QuoteRequest::query()->sole();
        $this->assertSame('Afi Mensah', $quote->name);
        $this->assertSame('+22890123456', $quote->phone);
        $this->assertTrue($quote->isPending());

        Mail::assertSent(QuoteRequestMail::class, fn (QuoteRequestMail $mail): bool => $mail->hasTo(config('site.payments.admin_email')) && $mail->hasReplyTo('afi@example.com'));
    }

    public function test_errors_bring_the_visitor_back_to_the_form(): void
    {
        $this->from(route('business'))->post(route('business.quote'), $this->form(['email' => 'pas-une-adresse', 'message' => '']))
            ->assertRedirect(route('business').'#devis')
            ->assertSessionHasErrors(['email', 'message']);

        $this->post(route('business.quote'), $this->form(['phone' => '12']))->assertSessionHasErrors('phone');
        $this->post(route('business.quote'), $this->form(['service' => 'inconnu']))->assertSessionHasErrors('service');

        $this->assertSame(0, QuoteRequest::query()->count());
    }

    public function test_the_honeypot_saves_nothing(): void
    {
        Mail::fake();

        $this->post(route('business.quote'), $this->form(['website' => 'http://spam']))->assertSessionHas('quote_sent');

        $this->assertSame(0, QuoteRequest::query()->count());
        Mail::assertNothingSent();
    }

    public function test_the_admin_lists_and_handles_quote_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $quote = QuoteRequest::query()->create(['name' => 'Kodjo', 'email' => 'k@example.com', 'service' => 'agent-ia', 'message' => 'Un agent pour mon service client.']);

        $this->actingAs($admin)->get(route('admin.quotes.index'))->assertOk()
            ->assertSee('Kodjo')
            ->assertSee('Agent IA')
            ->assertSee('Un agent pour mon service client.');

        $this->actingAs($admin)->post(route('admin.quotes.toggle', $quote))->assertRedirect();
        $this->assertFalse($quote->refresh()->isPending());
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::QuoteHandled->value)->exists());

        // Les modérateurs et les élèves n'y ont pas accès.
        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]))->get(route('admin.quotes.index'))->assertForbidden();
    }

    public function test_the_home_page_offers_the_quote_and_the_admin_edits_the_custom_work(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-business"', false)
            ->assertSee('Nous le faisons pour vous')
            ->assertSee(route('business').'#devis', false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), [
            ...(array) config('site.business'),
            'custom_work_sent' => '1',
            'custom_work_title' => 'Nos réalisations',
            'custom_work_intro' => '',
            'custom_work_items' => [['question' => 'Application mobile', 'answer' => 'Une application pour vos clients.']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nos réalisations', SiteSettings::get('site.custom_work')['title']);
        $this->get(route('business'))->assertSee('Application mobile')->assertDontSee('Un site plus complet');
        // Les choix du formulaire restent ceux de départ.
        $this->assertArrayHasKey('site-vitrine', (array) config('site.custom_work.services'));
    }

    public function test_the_privacy_policy_mentions_quote_requests(): void
    {
        $this->get(route('legal.privacy'))->assertOk()->assertSee('data-test="privacy-quote"', false);
    }
}
