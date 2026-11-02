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
 * Talkami (novembre 2026) : section de l'accueil, page /talkami, lien du menu, mention dans À propos, jamais dans le
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
            ->assertSee('data-test="menu-talkami"', false);
    }

    public function test_the_talkami_page_shows_the_facts_and_the_plans(): void
    {
        $html = (string) $this->get(route('talkami'))->assertOk()
            ->assertSee('Teacher Joe')
            ->assertSee('Teacher Rose')
            ->assertSee('de A1 à C2')
            ->assertSee(Money::fcfa(2000))
            ->assertSee(Money::fcfa(7500))
            ->assertSee(Money::fcfa(14000))
            ->assertSee('Rien n\'est prélevé automatiquement.')
            ->assertSee('Talkami a été conçu et créé par Joseph Ahiankonou, fondateur de Lomé IA School.')
            ->assertSee('https://wa.me/22872240231', false)
            ->getContent();

        $text = mb_strtolower(strip_tags($html));
        foreach (['garanti', '100 %', '100%', 'sans engagement'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text, $forbidden);
        }
    }

    public function test_talkami_is_in_about_but_never_in_the_footer(): void
    {
        $this->get(route('about'))->assertOk()->assertSee('data-test="about-talkami"', false);

        $footer = (string) file_get_contents(resource_path('views/site/partials/footer.blade.php'));
        $this->assertStringNotContainsStringIgnoringCase('talkami', $footer);
    }

    public function test_the_page_is_in_the_sitemap_and_indexable(): void
    {
        $this->assertContains('talkami', SearchEngines::INDEXABLE_ROUTES);
        $this->get(route('sitemap'))->assertOk()->assertSee(route('talkami'), false);
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

        $this->get(route('talkami'))->assertNotFound();
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
