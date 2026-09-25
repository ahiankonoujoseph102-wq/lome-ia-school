<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logo et icônes de Lomé IA School : fichiers générés depuis resources/brand/, utilisés partout.
 */
class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_files_are_kept_as_sources(): void
    {
        foreach (['icone.png', 'logo-horizontal.png', 'logo-vertical.png'] as $file) {
            $this->assertFileExists(resource_path('brand/'.$file));
        }
    }

    public function test_generated_icons_have_the_right_sizes(): void
    {
        $sizes = [
            'icons/favicon-16.png' => 16, 'icons/favicon-32.png' => 32, 'icons/favicon-48.png' => 48,
            'icons/apple-touch-icon.png' => 180, 'apple-touch-icon.png' => 180,
            'icons/icon-192.png' => 192, 'icons/icon-maskable-192.png' => 192,
            'icons/icon-512.png' => 512, 'icons/icon-maskable-512.png' => 512, 'icons/badge-96.png' => 96,
        ];

        foreach ($sizes as $file => $size) {
            [$width, $height] = getimagesize(public_path($file)) ?: [0, 0];
            $this->assertSame([$size, $size], [$width, $height], $file);
        }

        // favicon.ico : 16, 32 et 48 px.
        $ico = (string) file_get_contents(public_path('favicon.ico'));
        $this->assertSame([0, 1, 3], array_values(unpack('v3', substr($ico, 0, 6)) ?: []));
        $this->assertFileDoesNotExist(public_path('favicon.svg'), 'L\'ancienne icône provisoire est remplacée.');

        [$width, $height] = getimagesize(public_path('images/brand/partage.jpg')) ?: [0, 0];
        $this->assertSame([1200, 630], [$width, $height]);
        [, $logoHeight] = getimagesize(public_path('images/brand/logo-horizontal.png')) ?: [0, 0];
        $this->assertSame(120, $logoHeight);
    }

    public function test_pages_use_the_logo_and_the_icons(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(asset('images/brand/logo-horizontal.png'))
            ->assertSee('<link rel="icon" href="'.asset('icons/favicon-32.png').'" type="image/png" sizes="32x32">', false)
            ->assertSee('<link rel="apple-touch-icon" href="'.asset('icons/apple-touch-icon.png').'" sizes="180x180">', false)
            ->assertSee('<meta property="og:image:type" content="image/jpeg">', false);

        $this->get(route('about'))->assertOk()->assertSee(asset('images/brand/logo-vertical.jpg'));

        $this->get(route('og.site'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertJsonPath('icons.2.purpose', 'maskable')
            ->assertJsonPath('icons.3.src', asset('icons/icon-maskable-512.png'));

        $worker = (string) $this->get(route('pwa.service-worker'))->getContent();
        $this->assertStringContainsString('icons\\/badge-96.png', $worker);
        $this->assertStringContainsString('icons\\/icon-192.png', $worker);
    }
}
