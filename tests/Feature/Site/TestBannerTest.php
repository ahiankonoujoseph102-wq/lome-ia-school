<?php

namespace Tests\Feature\Site;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bandeau « Version de test » (TEST_BANNER dans .env) : mise en ligne temporaire pour l'équipe.
 */
class TestBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_banner_by_default(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="test-banner"', false);
    }

    public function test_banner_is_shown_on_every_kind_of_page_and_blocks_indexing(): void
    {
        config(['app.test_banner' => true, 'app.env' => 'production']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-test="test-banner"', false)
            ->assertSee('Version de test : le site est en cours de préparation.')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get(route('login'))->assertOk()->assertSee('data-test="test-banner"', false);

        $student = User::factory()->create();
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('data-test="test-banner"', false);

        $purchase = Purchase::factory()->for($student)->status(PurchaseStatus::Validated)->create();
        $this->actingAs($student)->get(route('student.purchases.receipt', $purchase))->assertOk()->assertSee('data-test="test-banner"', false);

        $this->get(route('robots'))->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    }

    public function test_banner_text_can_be_changed_in_env(): void
    {
        config(['app.test_banner' => true, 'app.test_banner_text' => 'Site de démonstration pour l\'équipe']);

        $this->get(route('home'))->assertSee('Site de démonstration pour l&#039;équipe', false);
    }
}
