<?php

namespace Tests\Feature\Site;

use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\Money;
use App\Support\SearchEngines;
use App\Support\Talkami;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Talkami (novembre 2026) : grande section de l'accueil (#talkami, pas de page à part : /talkami y redirige), lien du menu, mention dans À propos, jamais dans le
 * pied de page ; liens vers talkami.net avec utm_source=lome-ia-school ; réglages de l'admin ; aucune promesse.
 */
class TalkamiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_highlights_talkami_with_the_tracked_link(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-talkami"', false)
            ->assertSee('Évaluez gratuitement votre anglais en 5 minutes avec Talkami')
            ->assertSee('href="https://talkami.net/?utm_source=lome-ia-school"', false)
            ->assertSee('data-test="menu-talkami"', false)
            ->assertSee('data-test="home-talkami-logo"', false)
            ->assertSee('Passer mon évaluation gratuite');

        // Logo de Talkami fourni par Joseph : fichier léger, jamais redessiné.
        $this->assertFileExists(public_path(Talkami::LOGO));
        $this->assertLessThan(20 * 1024, filesize(public_path(Talkami::LOGO)));
    }

    public function test_the_home_section_shows_the_real_talkami_content(): void
    {
        $html = (string) $this->get(route('home'))->assertOk()
            ->assertSee('id="talkami"', false)
            ->assertSee('Teacher Joe')
            ->assertSee('Teacher Rose')
            ->assertSee('de A1 à C2')
            ->assertSee('data-test="home-talkami-steps"', false)
            ->assertSee('Passez l\'évaluation gratuite')
            ->assertSee(Money::fcfa(2000))
            ->assertSee(Money::fcfa(7500))
            ->assertSee(Money::fcfa(14000))
            ->assertSee('data-test="home-talkami-screen"', false)
            ->assertSee('data-test="home-talkami-screens"', false)
            ->assertDontSee('data-test="talkami-phone"', false)
            ->getContent();

        // Vraies captures d'écran de Talkami, légères ; aucune simulation de l'application.
        foreach (Talkami::SCREENS as $screen) {
            $this->assertStringContainsString($screen['image'], $html);
            $this->assertFileExists(public_path($screen['image']));
            $this->assertLessThan(60 * 1024, filesize(public_path($screen['image'])));
        }
        $this->assertFileDoesNotExist(resource_path('views/site/partials/talkami-phone.blade.php'));

        $section = mb_strtolower(strip_tags((string) strstr($html, 'data-test="home-talkami"')));
        foreach (['garanti', '100 %', '100%', 'sans engagement', 'formation gratuite en anglais'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $section, $forbidden);
        }
    }

    public function test_there_is_no_talkami_page_anymore(): void
    {
        $this->get('/talkami')->assertStatus(301)->assertRedirect('/#talkami');
        $this->assertNotContains('talkami', SearchEngines::INDEXABLE_ROUTES);
        $this->assertFileDoesNotExist(resource_path('views/site/talkami.blade.php'));
        $this->get(route('home'))->assertSee(route('home').'#talkami', false);
    }

    public function test_talkami_is_in_about_but_never_in_the_footer(): void
    {
        $this->get(route('about'))->assertOk()->assertSee('data-test="about-talkami"', false);

        $footer = (string) file_get_contents(resource_path('views/site/partials/footer.blade.php'));
        $this->assertStringNotContainsStringIgnoringCase('talkami', $footer);
    }

    public function test_the_admin_can_hide_talkami_and_change_the_plans(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.edit', 'talkami'))->assertOk()->assertSee('Afficher Talkami sur le site');

        $this->actingAs($admin)->put(route('admin.settings.update', 'talkami'), [
            'enabled' => '0',
            'url' => 'https://evil.example/?talkami.net',
            'plans' => [],
        ])->assertSessionHasErrors('url');

        $this->actingAs($admin)->put(route('admin.settings.update', 'talkami'), [
            'enabled' => '0',
            'url' => 'talkami.net/?utm_source=lome-ia-school',
            'whatsapp' => '+228 72 24 02 31',
            'plans' => [
                ['name' => 'Essentiel', 'price' => '8000', 'period' => 'par mois', 'detail' => '15 minutes par jour'],
                ['name' => '', 'price' => '', 'period' => '', 'detail' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $saved = (array) SiteSettings::get('site.talkami');
        $this->assertFalse($saved['enabled']);
        $this->assertSame('https://talkami.net/?utm_source=lome-ia-school', $saved['url']);
        $this->assertCount(1, $saved['plans']);
        $this->assertSame(8000, $saved['plans'][0]['price']);

        $this->get(route('home'))->assertOk()->assertDontSee('data-test="home-talkami"', false)->assertDontSee('data-test="menu-talkami"', false);
        $this->assertFalse(Talkami::enabled());
    }

    public function test_ama_knows_talkami(): void
    {
        $text = Talkami::assistantText();

        $this->assertStringContainsString('Joseph Ahiankonou', $text);
        $this->assertStringContainsString('https://talkami.net/?utm_source=lome-ia-school', $text);
        $this->assertStringContainsString('5 minutes', $text);
    }
}
