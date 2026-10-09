<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\FormationIllustrations as Illustrations;
use App\Support\FormationShare;
use Database\Seeders\FormationSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Illustrations des 17 formations (SVG converti en WebP), migrations 2026_10_10_100000_add_illustrations_to_formations
 * (16 formations) et 2026_10_11_100000_add_agents_ia_illustration (« Agents IA », seulement à partir du 8 octobre 2026).
 */
class FormationIllustrationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La base de test n'est migrée qu'une fois par série de tests, par le premier test qui tourne, à la vraie date du
     * jour. Depuis le 8 octobre 2026, la migration 2026_10_11_100000_add_agents_ia_illustration y enregistre alors
     * system.agents_illustration_done. Ce réglage est retiré au début de chaque test (retrait annulé à la fin du test
     * avec le reste) : ces tests ne dépendent ainsi ni de la date ni de l'ordre des tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('site_settings')->where('key', Illustrations::AGENTS_DONE_KEY)->delete();
        SiteSettings::flush();
    }

    private function migration(string $name): object
    {
        return require database_path('migrations/'.$name.'.php');
    }

    private function formation(string $slug, ?string $image = null): Formation
    {
        return Formation::factory()->create(['slug' => $slug, 'image' => $image, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_list_covers_the_seeded_formations_exactly(): void
    {
        // La formation gratuite du 10 octobre 2026 est créée par une migration, pas par le seeder : hors du compte.
        Formation::query()->where('slug', 'formation-gratuite-ia')->delete();
        $this->seed(FormationSeeder::class);

        $this->assertEqualsCanonicalizing(Formation::pluck('slug')->all(), Illustrations::all());
        $this->assertCount(17, Illustrations::all());
        $this->assertNotContains(Illustrations::AGENTS_SLUG, Illustrations::NOW);
    }

    public function test_each_illustration_is_a_light_webp_of_1600_by_1000(): void
    {
        foreach (Illustrations::all() as $slug) {
            $path = public_path('images/formations/'.Illustrations::file($slug));

            $this->assertFileExists($path);
            $this->assertLessThan(100 * 1024, filesize($path), $slug.' dépasse 100 Ko');
            $info = getimagesize($path);
            $this->assertSame([1600, 1000], array_slice($info, 0, 2), $slug);
            $this->assertSame('image/webp', $info['mime'], $slug);
        }
    }

    public function test_the_sources_hold_no_text_no_logo_and_no_dates_or_prices(): void
    {
        foreach (Illustrations::all() as $slug) {
            $path = resource_path('illustrations/formations/'.$slug.'.svg');
            $this->assertFileExists($path);
            $svg = (string) file_get_contents($path);

            // Le titre est écrit par le code, jamais par l'image ; pas d'image intégrée (logo, photo, visage).
            foreach (['<text', '<tspan', '<image', '<foreignObject', 'href='] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $svg, $slug.' contient '.$forbidden);
            }
            $this->assertDoesNotMatchRegularExpression('/openai|chatgpt|anthropic|claude|google|gemini|tiktok|facebook|youtube|upwork|fcfa|octobre/i', (string) preg_replace('/aria-label="[^"]*"/', '', $svg), $slug);
        }
    }

    public function test_the_first_migration_fills_the_sixteen_formations_without_agents_ia(): void
    {
        $migration = $this->migration('2026_10_10_100000_add_illustrations_to_formations');
        foreach (Illustrations::all() as $slug) {
            $this->formation($slug);
        }
        $other = $this->formation('autre-formation');

        $migration->up();
        $migration->up();

        foreach (Illustrations::NOW as $slug) {
            $formation = Formation::where('slug', $slug)->first();
            $this->assertSame($slug.'.webp', $formation->image);
            $this->assertStringContainsString('images/formations/'.$slug.'.webp', $formation->image_url);
        }
        $this->assertNull(Formation::where('slug', Illustrations::AGENTS_SLUG)->first()->image);
        $this->assertNull($other->fresh()->image);
    }

    public function test_an_image_uploaded_in_the_admin_is_never_replaced(): void
    {
        $uploaded = $this->formation('ebooks-livres-professionnels-ia', 'formations/photo-de-joseph.jpg');
        $legacy = $this->formation('creation-saas-ia', 'ancienne.jpg');

        $this->migration('2026_10_10_100000_add_illustrations_to_formations')->up();

        $this->assertSame('formations/photo-de-joseph.jpg', $uploaded->fresh()->image);
        $this->assertSame('ancienne.jpg', $legacy->fresh()->image);
    }

    public function test_agents_ia_keeps_its_current_visual_and_dated_share_preview_while_the_live_is_on(): void
    {
        Carbon::setTestNow('2026-10-07 12:00:00');
        $agents = $this->formation(Illustrations::AGENTS_SLUG);
        $this->migration('2026_10_10_100000_add_illustrations_to_formations')->up();
        $this->migration('2026_10_11_100000_add_agents_ia_illustration')->up();
        Artisan::call('app:apply-formation-illustrations');

        $this->assertNull($agents->fresh()->image);
        $this->get(route('formations.show', $agents))->assertOk()
            ->assertSee('formation-en-direct', false)
            ->assertDontSee('creation-agents-ia.webp', false)
            ->assertSee('og/formations/creation-agents-ia.png', false);
        $this->assertSame('image/png', FormationShare::imageType($agents->fresh()));
    }

    public function test_agents_ia_gets_its_illustration_from_8_october_only_if_it_still_has_no_image(): void
    {
        Carbon::setTestNow('2026-10-07 23:59:59');
        $this->assertFalse(Illustrations::agentsAreDue());
        Carbon::setTestNow('2026-10-08 00:00:00');
        $this->assertTrue(Illustrations::agentsAreDue());

        $agents = $this->formation(Illustrations::AGENTS_SLUG);
        $this->artisan('app:apply-formation-illustrations')->expectsOutput('Illustration d\'« Agents IA » posée.')->assertSuccessful();

        $this->assertSame('creation-agents-ia.webp', $agents->fresh()->image);
        $this->assertStringContainsString('images/formations/creation-agents-ia.webp', $agents->fresh()->image_url);
        $this->assertTrue(SiteSettings::get(Illustrations::AGENTS_DONE_KEY));
    }

    public function test_agents_ia_with_an_admin_image_is_never_replaced_after_the_date(): void
    {
        Carbon::setTestNow('2026-10-09 10:00:00');
        $agents = $this->formation(Illustrations::AGENTS_SLUG, 'formations/mon-image.jpg');

        $this->migration('2026_10_11_100000_add_agents_ia_illustration')->up();
        Artisan::call('app:apply-formation-illustrations');

        $this->assertSame('formations/mon-image.jpg', $agents->fresh()->image);
    }

    public function test_a_deploy_before_the_date_does_nothing_and_the_nightly_task_applies_it_later(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');
        $agents = $this->formation(Illustrations::AGENTS_SLUG);

        // Mise en ligne avant le 8 octobre : la migration ne fait rien (et ne note rien).
        $this->migration('2026_10_11_100000_add_agents_ia_illustration')->up();
        $this->assertNull($agents->fresh()->image);
        $this->assertNull(SiteSettings::get(Illustrations::AGENTS_DONE_KEY));

        Carbon::setTestNow('2026-10-07 23:59:00');
        Artisan::call('app:apply-formation-illustrations');
        $this->assertNull($agents->fresh()->image);

        Carbon::setTestNow('2026-10-08 00:06:00');
        Artisan::call('app:apply-formation-illustrations');
        $this->assertSame('creation-agents-ia.webp', $agents->fresh()->image);
    }

    public function test_a_deploy_after_the_date_applies_it_through_the_migration(): void
    {
        Carbon::setTestNow('2026-10-12 08:00:00');
        $agents = $this->formation(Illustrations::AGENTS_SLUG);

        $this->migration('2026_10_11_100000_add_agents_ia_illustration')->up();

        $this->assertSame('creation-agents-ia.webp', $agents->fresh()->image);
    }

    public function test_removing_the_agents_illustration_in_the_admin_is_respected_afterwards(): void
    {
        Carbon::setTestNow('2026-10-08 09:00:00');
        $agents = $this->formation(Illustrations::AGENTS_SLUG);
        Artisan::call('app:apply-formation-illustrations');
        $this->assertNotNull($agents->fresh()->image);

        $agents->fresh()->update(['image' => null]);
        Carbon::setTestNow('2026-10-09 00:06:00');
        Artisan::call('app:apply-formation-illustrations');

        $this->assertNull($agents->fresh()->image);
    }

    public function test_the_nightly_task_is_scheduled_at_lome_time(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'app:apply-formation-illustrations'));

        $this->assertNotNull($event);
        $this->assertSame('5 0 * * *', $event->expression);
        $this->assertSame('Africa/Lome', (string) $event->timezone);
    }

    public function test_the_illustration_shows_in_the_catalogue_the_page_the_checkout_and_the_share_preview(): void
    {
        $formation = $this->formation('ebooks-livres-professionnels-ia');
        $this->migration('2026_10_10_100000_add_illustrations_to_formations')->up();
        $formation = $formation->fresh();

        $this->get(route('formations.index'))->assertOk()->assertSee('images/formations/ebooks-livres-professionnels-ia.webp', false);
        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('images/formations/ebooks-livres-professionnels-ia.webp', false)
            ->assertSee('og/formations/ebooks-livres-professionnels-ia.jpg', false);
        $this->actingAs(User::factory()->create())->get(route('checkout.show', $formation))->assertOk()
            ->assertSee('images/formations/ebooks-livres-professionnels-ia.webp', false);

        $this->assertSame('image/jpeg', FormationShare::imageType($formation));
        $response = $this->get(route('og.formation.photo', $formation))->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame([1200, 630], array_slice(getimagesize($response->baseResponse->getFile()->getPathname()), 0, 2));
    }

    public function test_the_admin_can_remove_or_replace_the_illustration_without_deleting_the_file(): void
    {
        $formation = $this->formation('creation-saas-ia', 'creation-saas-ia.webp');
        $admin = User::factory()->admin()->create();
        $fields = [
            'title' => $formation->title,
            'description' => 'Description.',
            'price' => 25000,
            'category' => $formation->category->value,
        ];

        $this->actingAs($admin)->put(route('admin.formations.update', $formation), $fields + ['remove_image' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($formation->fresh()->image);

        $formation->update(['image' => 'creation-saas-ia.webp']);
        Storage::fake('public');
        $this->actingAs($admin)->put(route('admin.formations.update', $formation), $fields + ['image' => UploadedFile::fake()->image('nouvelle.jpg', 1600, 1000)])->assertSessionHasNoErrors();
        $this->assertStringStartsWith('formations/', (string) $formation->fresh()->image);

        $this->assertFileExists(public_path('images/formations/creation-saas-ia.webp'));
    }
}
