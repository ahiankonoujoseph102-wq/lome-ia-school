<?php

namespace Tests\Feature\Site;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bandeau « Version de test » (TEST_BANNER) et blocage de Google (NOINDEX) : deux réglages séparés.
 */
class TestBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_banner_by_default(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="test-banner"', false);
    }

    public function test_banner_is_shown_on_every_kind_of_page(): void
    {
        config(['app.test_banner' => true]);
        $this->app['env'] = 'production';

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-test="test-banner"', false)
            ->assertSee('Version de test : le site est en cours de préparation.')
            // Le bandeau seul ne bloque plus Google : c'est NOINDEX qui décide.
            ->assertDontSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertHeaderMissing('X-Robots-Tag');

        $this->get(route('login'))->assertOk()->assertSee('data-test="test-banner"', false);

        $student = User::factory()->create();
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('data-test="test-banner"', false);

        $purchase = Purchase::factory()->for($student)->status(PurchaseStatus::Validated)->create();
        $this->actingAs($student)->get(route('student.purchases.receipt', $purchase))->assertOk()->assertSee('data-test="test-banner"', false);

        $this->get(route('robots'))->assertSee('Sitemap:');
    }

    public function test_noindex_blocks_search_engines_without_the_banner(): void
    {
        config(['app.noindex' => true, 'app.test_banner' => false]);
        $this->app['env'] = 'production';

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-test="test-banner"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get(route('formations.index'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('robots'))->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    }

    public function test_the_final_site_is_indexed_in_production(): void
    {
        config(['app.noindex' => false, 'app.test_banner' => false]);
        $this->app['env'] = 'production';

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertHeaderMissing('X-Robots-Tag');

        $this->get(route('robots'))->assertDontSee("Disallow: /\n", false)->assertSee('Sitemap:');
    }

    public function test_banner_text_can_be_changed_in_env(): void
    {
        config(['app.test_banner' => true, 'app.test_banner_text' => 'Site de démonstration pour l\'équipe']);

        $this->get(route('home'))->assertSee('Site de démonstration pour l&#039;équipe', false);
    }
}
