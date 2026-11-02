<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Animations utiles (novembre 2026) : chiffres clés qui montent, reflet sur les boutons d'achat, cartes du catalogue
 * qui apparaissent au filtrage ; et logo servi en WebP. Rien ne dépend d'une animation pour être lu.
 */
class SiteMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_figures_count_up_and_the_main_buttons_shine(): void
    {
        $formation = Formation::factory()->create(['price' => 10000]);

        $home = (string) $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(4, substr_count($home, 'data-count-up'));
        // Le chiffre final est écrit dans la page : visible sans script.
        $this->assertMatchesRegularExpression('/data-count-up[^>]*>\s*1 000\+/', $home);
        $this->assertStringContainsString('btn-gold btn-shine', $home);

        $this->get(route('about'))->assertOk()->assertSee('data-count-up', false);
        $this->get(route('formations.show', $formation))->assertOk()->assertSee('btn-gold btn-shine', false);
        $this->get(route('formations.index'))->assertOk()->assertSee('x-transition:enter-start="opacity-0 translate-y-2"', false);

        $css = (string) file_get_contents(resource_path('css/site.css'));
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s*html:not\(\.force-dark\) \.btn-shine::after/', $css);
    }

    public function test_the_logo_is_served_as_webp_with_the_png_as_fallback(): void
    {
        $this->assertFileExists(public_path('images/brand/logo-horizontal.webp'));
        $this->assertLessThan(15_000, filesize(public_path('images/brand/logo-horizontal.webp')));

        $this->get(route('home'))->assertOk()
            ->assertSee('<source srcset="'.asset('images/brand/logo-horizontal.webp').'" type="image/webp">', false)
            ->assertSee('src="'.asset('images/brand/logo-horizontal.png').'"', false);
    }

    public function test_count_up_logic(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/count-up.js.');
        }

        $module = json_encode('file://'.resource_path('js/count-up.js'));
        $program = <<<JS
            import { parseCount, countText } from {$module};
            const thousand = parseCount('1 000+');
            console.log(JSON.stringify({
                thousand,
                eighteen: parseCount('18'),
                year: parseCount('2023'),
                text: parseCount('À vie'),
                start: countText(thousand, 0),
                middle: countText(thousand, 0.5),
                end: countText(thousand, 1),
            }));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame(['value' => 1000, 'suffix' => '+', 'separator' => ' '], $out['thousand']);
        $this->assertSame(18, $out['eighteen']['value']);
        $this->assertNull($out['year']);
        $this->assertNull($out['text']);
        $this->assertSame('0+', $out['start']);
        $this->assertSame('875+', $out['middle']);
        $this->assertSame('1 000+', $out['end']);
    }
}
