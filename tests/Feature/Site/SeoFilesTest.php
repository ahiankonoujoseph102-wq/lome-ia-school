<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_formation_share_image_is_a_1200_by_630_png(): void
    {
        $formation = Formation::factory()->create();

        $response = $this->get(route('og.formation', $formation));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname()) ?: [0, 0];
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_site_share_image_is_available(): void
    {
        $this->get(route('og.site'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_inactive_formation_has_no_share_image(): void
    {
        $this->get(route('og.formation', Formation::factory()->inactive()->create()))->assertNotFound();
    }

    public function test_sitemap_lists_pages_and_active_formations(): void
    {
        $active = Formation::factory()->create();
        $inactive = Formation::factory()->inactive()->create();

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('formations.index'), false)
            ->assertSee(route('formations.show', $active), false)
            ->assertDontSee(route('formations.show', $inactive), false);
    }

    public function test_robots_blocks_indexing_outside_production(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertSee('Disallow: /');
    }
}
