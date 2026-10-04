<?php

namespace Tests\Feature\Site;

use App\Enums\PurchaseStatus;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Le site reste toujours sombre et n'est jamais recoloré par le mode sombre forcé de Chrome
 * (Tecno, Infinix, Xiaomi, Samsung…), y compris avec un téléphone en mode clair.
 * Vérifié aussi par des captures dans les 4 combinaisons (téléphone clair ou sombre, avec ou sans forçage).
 */
class SiteColorSchemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_declare_only_dark_and_detect_forced_dark_mode(): void
    {
        $formation = Formation::factory()->create(['slug' => 'automatisation-ia']);

        foreach ([route('home'), route('formations.show', $formation), route('checkout.show', $formation), route('affiliate.program')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('<meta name="color-scheme" content="only dark">', $html, $url);
            // Détection du mode sombre forcé (couleur système « canvas » qui n'est plus blanche) avant tout affichage.
            $this->assertMatchesRegularExpression("/<script>\\s*\\(\\(\\) => \\{.*background-color:canvas;color-scheme:light.*rgb\\(18, 18, 18\\).*prefers-color-scheme: dark.*'force-dark'.*<\\/script>.*<link[^>]+build\\/assets\\/site/s", $html, $url);
        }
    }

    public function test_css_compensates_every_element_chrome_would_recolor(): void
    {
        $css = (string) file_get_contents(resource_path('css/site.css'));

        $this->assertMatchesRegularExpression('/^:root \{\s*color-scheme: only dark;/m', $css);
        $this->assertStringContainsString('--color-veil: #ffffff;', $css);
        $this->assertMatchesRegularExpression('/html\.force-dark \{[^}]*--color-veil: rgb\(198 198 198\);/', $css);

        foreach (['.force-dark .btn-gold', '.force-dark .bg-gold-400.text-night-950', '.force-dark .bg-emerald-500.text-night-950', '.force-dark .text-gradient', '.force-dark .method-dot', '.force-dark .chat-file-icon', '.force-dark .qr-code'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector);
        }

        // Les règles de compensation sont hors des couches Tailwind, pour garder la priorité.
        $unlayered = (string) preg_replace('/@layer\s[^{]+\{(?:[^{}]|\{[^{}]*\})*\}/', '', $css);
        $this->assertStringContainsString('.force-dark .btn-gold {', $unlayered);
    }

    public function test_site_views_never_use_translucent_white_overlays_that_chrome_would_darken(): void
    {
        $files = collect(File::allFiles(resource_path('views/site')))
            ->merge(File::allFiles(resource_path('views/components/site')))
            ->push(new \SplFileInfo(resource_path('views/layouts/site.blade.php')))
            ->push(new \SplFileInfo(resource_path('css/site.css')))
            ->push(new \SplFileInfo(resource_path('js/site.js')))
            ->push(new \SplFileInfo(resource_path('js/chat.js')));

        $offenders = [];

        foreach ($files as $file) {
            $content = (string) file_get_contents($file->getPathname());
            // Le reçu imprimable reste clair ; le QR code garde son fond blanc (compensé par .qr-code).
            $content = str_replace('qr-code mt-3 inline-block rounded-2xl bg-white', '', $content);

            if (str_contains($file->getPathname(), 'receipt.blade.php')) {
                continue;
            }

            if (preg_match_all('/(?<![\w-])(?:[\w-]+:)*(?:bg|ring|border|border-[trblxy]|divide|outline|from|via|to)-white(?![\w-])/', $content, $matches)) {
                $offenders[$file->getRelativePathname() ?: $file->getPathname()] = array_unique($matches[0]);
            }
        }

        $this->assertSame([], $offenders, 'Utilisez la couleur « veil » (bg-veil/5, border-veil/10…) au lieu du blanc : Chrome assombrit le blanc en mode sombre forcé.');
    }

    public function test_formation_visual_is_drawn_in_css_not_svg(): void
    {
        $component = (string) file_get_contents(resource_path('views/components/site/formation-cover.blade.php'));

        $this->assertStringNotContainsString('<svg', $component);
        $this->assertStringContainsString('cover-art', $component);
    }

    public function test_payment_method_dots_get_their_inverse_color(): void
    {
        $method = PaymentMethods::find('tg-mixx');

        $this->assertNotNull($method);
        $this->assertSame('#facc15', $method->color);
        $this->assertSame('#0533ea', $method->inverseColor());
        $this->assertSame('#0533ea', $method->toArray()['inverse_color']);
    }

    public function test_printable_receipt_stays_light(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->status(PurchaseStatus::Validated)->create(['verified_at' => now()]);

        $html = (string) $this->actingAs($student)->get(route('student.purchases.receipt', $purchase))->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="color-scheme" content="only light">', $html);
        $this->assertStringNotContainsString('force-dark', $html);
    }
}
