<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use App\Models\User;
use App\Support\SiteDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design clair dans le style de Talkami (novembre 2026, demandé par Joseph) et retour possible à l'ancien design
 * sombre depuis l'admin, sans mise en ligne.
 */
class SiteDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_is_light_by_default_and_opts_out_of_chrome_forced_dark(): void
    {
        $formation = Formation::factory()->create(['price' => 10000]);

        foreach ([route('home'), route('formations.index'), route('formations.show', $formation), route('consultation')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('data-theme="light"', false)
                ->assertSee('<meta name="color-scheme" content="only light">', false)
                ->assertSee('<meta name="theme-color" content="#ffffff">', false);
        }

        $css = (string) file_get_contents(resource_path('css/site.css'));
        $this->assertStringContainsString("html[data-theme='light'] {", $css);
        $this->assertStringContainsString('color-scheme: only light;', $css);
        $this->assertStringContainsString("html[data-theme='light'] .theme-island {", $css);
    }

    public function test_dark_blocks_keep_their_dark_colours(): void
    {
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        // Pied de page et section Talkami : blocs bleu nuit.
        $this->assertMatchesRegularExpression('/<footer class="theme-island /', $html);
        $this->assertMatchesRegularExpression('/class="theme-island relative overflow-hidden rounded-\[2rem\] bg-\[#101c45\]/', $html);
        // En-tête clair : l'icône et le nom en texte (le logo, en texte blanc, n'est jamais recoloré).
        $this->assertStringContainsString('Lomé <span class="text-gold-400', $html);
    }

    public function test_the_admin_can_bring_back_the_dark_design_without_a_release(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.edit', 'fond-anime'))->assertOk()
            ->assertSee('data-test="site-design-choice"', false)
            ->assertSee('value="light" checked', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'fond-anime'), ['enabled' => '1', 'theme' => 'dark'])->assertRedirect();
        $this->assertSame('dark', SiteDesign::theme());
        $this->get(route('home'))->assertSee('data-theme="dark"', false)->assertSee('content="only dark"', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'fond-anime'), ['enabled' => '1', 'theme' => 'light'])->assertRedirect();
        $this->get(route('home'))->assertSee('data-theme="light"', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'fond-anime'), ['enabled' => '1', 'theme' => 'rose'])->assertSessionHasErrors('theme');
    }

    public function test_group_conversations_stay_dark(): void
    {
        $this->assertSame('dark', SiteDesign::theme('chat'));
        $this->assertSame('light', SiteDesign::theme('student'));
    }
}
