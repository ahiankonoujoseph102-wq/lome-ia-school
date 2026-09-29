<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\User;
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
            'transcript' => '',
            'title' => $business['title'],
            'intro' => $business['intro'],
            'services' => $business['services'],
            'whatsapp_number' => '+228 72 24 02 31',
            'whatsapp_message' => $business['whatsapp_message'],
            'home_title' => $business['home_title'],
            'home_text' => $business['home_text'],
        ], $overrides);
    }

    public function test_page_lists_the_services_and_the_whatsapp_button_without_video_by_default(): void
    {
        $response = $this->get(route('business'))->assertOk()
            ->assertSee('Entreprises et centres de formation')
            ->assertSee('Afrique de l&#039;Ouest', false)
            ->assertDontSee('data-test="business-video"', false)
            ->assertDontSee('Lire la transcription')
            ->assertDontSee('youtube');

        foreach (self::SERVICES as $service) {
            $response->assertSee($service);
        }

        $message = rawurlencode('Bonjour, je représente un centre de formation / une entreprise et je souhaite discuter d\'un accompagnement en IA.');
        $response->assertSee('https://wa.me/22872240231?text='.$message, false)
            ->assertSee('+228 72 24 02 31');
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

    public function test_video_and_transcript_are_set_in_the_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.edit', 'entreprises'))->assertOk()->assertSee('Lien de la vidéo YouTube');

        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form([
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'transcript' => "Bonjour et bienvenue.\r\nDeuxième ligne.\r\n\r\nNouveau <b>paragraphe</b>.",
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->exists());
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', config('site.business.video_url'));

        $this->get(route('business'))->assertOk()
            ->assertSee('data-test="business-video"', false)
            ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
            ->assertSee('Lire la transcription')
            ->assertSee('Bonjour et bienvenue.<br />', false)
            ->assertSee('<p>Nouveau &lt;b&gt;paragraphe&lt;/b&gt;.</p>', false);

        // Lien vidé : bloc vidéo (et transcription) masqués.
        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form(['transcript' => 'Texte']))->assertSessionHasNoErrors();
        $this->get(route('business'))->assertDontSee('data-test="business-video"', false)->assertDontSee('Lire la transcription');
    }

    public function test_invalid_video_link_and_number_are_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form(['video_url' => 'https://vimeo.com/123']))->assertSessionHasErrors('video_url');
        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form(['whatsapp_number' => '12']))->assertSessionHasErrors('whatsapp_number');
        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form(['services' => ['', ' ']]))->assertSessionHasErrors('services');
    }

    public function test_texts_whatsapp_and_home_block_are_editable(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'entreprises'), $this->form([
            'whatsapp_number' => '+229 01 97 00 00 01',
            'whatsapp_message' => 'Bonjour, message modifié.',
            'services' => ['Service A', '', 'Service B'],
            'home_title' => 'Titre du bloc modifié',
        ]))->assertSessionHasNoErrors();

        $this->get(route('business'))
            ->assertSee('https://wa.me/2290197000001?text='.rawurlencode('Bonjour, message modifié.'), false)
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
