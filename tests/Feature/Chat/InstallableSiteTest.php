<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InstallableSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_makes_the_site_installable(): void
    {
        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('lang', 'fr')
            ->assertJsonPath('icons.0.src', '/icons/icon-192.png')
            ->assertJsonPath('icons.2.purpose', 'maskable');

        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'badge-96.png'] as $icon) {
            $this->assertFileExists(public_path('icons/'.$icon));
        }

        $this->get('/')->assertSee('rel="manifest"', false)->assertSee('apple-mobile-web-app-capable', false);
    }

    public function test_service_worker_is_served_fresh_and_never_caches_private_content(): void
    {
        $response = $this->get('/sw.js')->assertOk();

        $this->assertStringStartsWith('application/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $script = $response->getContent();
        $this->assertStringContainsString("const CACHEABLE_PREFIXES = ['/build/assets/', '/icons/'];", $script);
        $this->assertStringContainsString("request.mode === 'navigate'", $script);
        $this->assertStringNotContainsString('mon-espace/groupes/', substr($script, 0, (int) strpos($script, "self.addEventListener('push'")));
    }

    /**
     * Exécute la règle de cache du vrai service worker avec Node.js, sur des adresses privées et publiques.
     */
    public function test_service_worker_cache_rule_rejects_every_private_request(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter le service worker.');
        }

        $script = $this->get('/sw.js')->getContent();
        $checks = [
            ['https://site.test/mon-espace/groupes/automatisation-ia', 'navigate', false],
            ['https://site.test/mon-espace/groupes/automatisation-ia/messages?after=12', 'cors', false],
            ['https://site.test/mon-espace/groupes/automatisation-ia/fichiers/5', 'no-cors', false],
            ['https://site.test/mon-espace/achats/3/preuve', 'no-cors', false],
            ['https://site.test/formations/automatisation-ia/lecons/1/fichiers/2', 'no-cors', false],
            ['https://site.test/build/assets/site.js?v=1', 'no-cors', false],
            ['https://autre-site.test/build/assets/site.js', 'no-cors', false],
            ['https://site.test/', 'navigate', false],
            ['https://site.test/build/assets/site-D2bh5VuI.js', 'no-cors', true],
            ['https://site.test/icons/icon-192.png', 'no-cors', true],
        ];

        $program = 'const self = { location: { origin: "https://site.test" }, addEventListener() {} };'
            ."\n".$script."\n"
            .'const checks = '.json_encode($checks).';'
            .'const results = checks.map(([url, mode]) => isCacheable({ url, mode, method: "GET" }));'
            .'results.push(isCacheable({ url: "https://site.test/icons/icon-192.png", mode: "no-cors", method: "POST" }));'
            .'console.log(JSON.stringify(results));';

        $process = new Process([$node, '-e', $program]);
        $process->mustRun();

        $expected = array_column($checks, 2);
        $expected[] = false;
        $this->assertSame($expected, json_decode(trim($process->getOutput()), true));
    }
}
