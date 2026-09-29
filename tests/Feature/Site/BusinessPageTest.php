<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\YouTube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page « Entreprises et centres de formation » (/entreprises) et ses réglages dans l'admin.
 */
class BusinessPageTest extends TestCase
{
    use RefreshDatabase;

    private const array SERVICES = [
        'L\'entraînement et l\'amélioration des systèmes d\'IA',
        'La création de contenu avec l\'intelligence artificielle',
        'L\'automatisation des tâches et des processus',
        'La création de sites web',
        'Le développement d\'agents IA',
        'Le copywriting',
        'Le marketing digital',
        'L\'intégration de l\'IA dans les entreprises',
        'La formation des employés',
        'L\'intégration de formations en IA dans les programmes des centres de formation',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        $business = (array) config('site.business');

        return array_replace([
            'video_url' => '',
            'title' => $business['title'],
            'intro' => $business['intro'],
            'services' => $business['services'],
            'home_title' => $business['home_title'],
            'home_text' => $business['home_text'],
        ], $overrides);
    }

    public function test_page_lists_the_services_and_the_booking_button_without_video_by_default(): void
    {
        $response = $this->get(route('business'))->assertOk()
            ->assertSee('Entreprises et centres de formation')
            ->assertSee('Afrique de l&#039;Ouest', false)
            ->assertDontSee('data-test="business-video"', false)
            ->assertDontSee('youtube');

        foreach (self::SERVICES as $service) {
            $response->assertSee($service);
        }

        $response->assertSee('Réserver un appel gratuit')
            ->assertSee('data-test="business-booking"', false)
            ->assertSee(route('consultation', ['profil' => 'entreprise']).'#reserver', false);
    }

    public function test_page_has_no_whatsapp_button_nor_transcript(): void
    {
        $html = (string) $this->get(route('business'))->assertOk()->getContent();

        // Seul le bouton WhatsApp habituel de l'en-tête (et le bouton flottant sur grand écran) reste, avec le message du site.
        $this->assertStringNotContainsString('je représente un centre de formation', $html);
        $this->assertStringNotContainsString('Écrire sur WhatsApp', $html);
        $this->assertStringNotContainsString('Lire la transcription', $html);
        $this->assertStringNotContainsString('btn-whatsapp', $html);
        $this->assertSame(substr_count((string) $this->get(route('about'))->getContent(), 'https://wa.me/'), substr_count($html, 'https://wa.me/'));

        $home = (string) $this->get(route('home'))->getContent();
        $this->assertStringNotContainsString('je représente un centre de formation', $home);
    }

    public function test_page_has_no_invented_figure_or_company_name_in_its_intro(): void
    {
        foreach ((array) config('site.business.intro') as $paragraph) {
            $this->assertDoesNotMatchRegularExpression('/\d/', $paragraph);
        }
    }

    public function test_links_in_menu_footer_home_and_sitemap(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-business"', false)
            ->assertSee('Vous êtes un centre de formation ou une entreprise ?')
            ->assertSee(route('business'));

        $this->get(route('contact'))->assertOk()->assertSee('>Entreprises<', false)->assertSee('Entreprises et centres de formation');
        $this->get(route('sitemap'))->assertSee(route('business'));
    }

    public function test_video_is_set_in_the_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.edit', 'entreprises'))->assertOk()
            ->assertSee('Lien de la vidéo YouTube')
            ->assertDontSee('name="transcript"', false)
            ->assertDontSee('name="whatsapp_number"', false)
            ->assertDontSee('name="whatsapp_message"', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form([
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->exists());
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', config('site.business.video_url'));

        $this->get(route('business'))->assertOk()
            ->assertSee('data-test="business-video"', false)
            ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false);

        // Lien vidé : bloc vidéo masqué.
        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form())->assertSessionHasNoErrors();
        $this->get(route('business'))->assertDontSee('data-test="business-video"', false);
    }

    public function test_shorts_links_are_detected_as_vertical(): void
    {
        $this->assertTrue(YouTube::isShorts('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertTrue(YouTube::isShorts('https://youtube.com/shorts/dQw4w9WgXcQ?si=abc'));
        $this->assertFalse(YouTube::isShorts('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertFalse(YouTube::isShorts('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

        // Sans choix : automatique.
        $this->assertSame('vertical', YouTube::format('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertSame('horizontal', YouTube::format('https://youtu.be/dQw4w9WgXcQ', ''));
        // Le choix de l'admin l'emporte toujours.
        $this->assertSame('horizontal', YouTube::format('https://www.youtube.com/shorts/dQw4w9WgXcQ', 'horizontal'));
        $this->assertSame('vertical', YouTube::format('https://youtu.be/dQw4w9WgXcQ', 'vertical'));
    }

    public function test_vertical_video_is_shown_in_a_9_16_frame_without_black_bars(): void
    {
        SiteSettings::save(['site.business' => [...(array) config('site.business'), 'video_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'video_format' => '']]);

        $this->get(route('business'))->assertOk()
            ->assertSee('data-format="vertical"', false)
            ->assertSee('aspect-[9/16]', false)
            ->assertSee('max-w-[400px]', false)
            ->assertDontSee('aspect-video', false)
            // Chargement au clic : miniature d'abord, lecteur seulement après le clic.
            ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
            ->assertSee('<template x-if="playing">', false)
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_horizontal_video_keeps_the_16_9_frame(): void
    {
        SiteSettings::save(['site.business' => [...(array) config('site.business'), 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_format' => '']]);

        $this->get(route('business'))->assertOk()
            ->assertSee('data-format="horizontal"', false)
            ->assertSee('aspect-video', false)
            ->assertDontSee('aspect-[9/16]', false);
    }

    public function test_the_format_is_chosen_in_the_admin(): void
    {
        $admin = User::factory()->admin()->create();

        // Lien Shorts : « Vertical » présélectionné.
        SiteSettings::save(['site.business' => [...(array) config('site.business'), 'video_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'video_format' => '']]);
        $this->actingAs($admin)->get(route('admin.settings.edit', 'entreprises'))->assertOk()
            ->assertSee('Format de la vidéo')
            ->assertSee('Vertical (téléphone)')
            ->assertSee('value="vertical" x-model="format" class="sr-only" checked', false);

        // Vidéo verticale publiée avec un lien classique : le choix de l'admin l'emporte.
        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form([
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'video_format' => 'vertical',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('vertical', config('site.business.video_format'));
        $this->get(route('business'))->assertSee('data-format="vertical"', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form([
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'video_format' => 'carre',
        ]))->assertSessionHasErrors('video_format');
    }

    public function test_invalid_video_link_and_empty_services_are_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form(['video_url' => 'https://vimeo.com/123']))->assertSessionHasErrors('video_url');
        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form(['services' => ['', ' ']]))->assertSessionHasErrors('services');
    }

    public function test_texts_and_home_block_are_editable(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form([
            'services' => ['Service A', '', 'Service B'],
            'home_title' => 'Titre du bloc modifié',
        ]))->assertSessionHasNoErrors();

        $this->get(route('business'))
            ->assertSee('Service A')
            ->assertSee('Service B')
            ->assertDontSee('Le copywriting');
        $this->get(route('home'))->assertSee('Titre du bloc modifié');
    }

    public function test_only_the_admin_can_edit_the_page(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->get(route('admin.settings.edit', 'entreprises'))->assertForbidden();
        $this->actingAs($moderator)->put(route('admin.settings.update', 'entreprises'), $this->form())->assertForbidden();
    }

    public function test_hosting_text_of_the_legal_pages_is_editable(): void
    {
        $this->get(route('legal.notice'))->assertOk()->assertSee('Laravel Cloud')->assertSee('Europe (Londres, Royaume-Uni)');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Laravel Cloud')->assertSee('Europe (Londres, Royaume-Uni)');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'legal'))->assertOk()->assertSee('Hébergeur du site');

        $this->actingAs($admin)->put(route('admin.settings.update', 'legal'), ['host' => '', 'data_location' => ''])->assertSessionHasErrors(['host', 'data_location']);
        $this->actingAs($admin)->put(route('admin.settings.update', 'legal'), [
            'host' => 'Hostinger International Ltd. (https://www.hostinger.fr)',
            'data_location' => 'Europe (Lituanie)',
        ])->assertSessionHasNoErrors();

        $this->get(route('legal.notice'))->assertSee('Hostinger International Ltd.')->assertSee('Europe (Lituanie)')->assertDontSee('Laravel Cloud');
        $this->get(route('legal.privacy'))->assertSee('Hostinger International Ltd.')->assertSee('Europe (Lituanie)');
        $this->assertTrue(AdminActivity::query()->where('description', 'like', '%Pages légales%')->exists());
    }
}
