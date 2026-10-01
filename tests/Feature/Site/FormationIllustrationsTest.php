<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use App\Models\User;
use App\Support\FormationShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Illustrations des formations « Agents IA », « ChatGPT & Claude » et « Ebooks et livres pro » (SVG converti en WebP)
 * et migration 2026_10_10_100000_add_illustrations_to_formations.
 */
class FormationIllustrationsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_10_100000_add_illustrations_to_formations.php');
    }

    public function test_each_illustration_is_a_light_webp_of_1600_by_1000(): void
    {
        foreach ($this->migration()::ILLUSTRATIONS as $file) {
            $path = public_path('images/formations/'.$file);

            $this->assertFileExists($path);
            $this->assertLessThan(100 * 1024, filesize($path), $file.' dépasse 100 Ko');
            $this->assertSame('RIFF', substr((string) file_get_contents($path, false, null, 0, 4), 0, 4));
            $this->assertSame([1600, 1000], array_slice(getimagesize($path), 0, 2));
            $this->assertSame('image/webp', getimagesize($path)['mime']);
        }
    }

    public function test_the_sources_hold_no_text_no_logo_and_no_dates_or_prices(): void
    {
        foreach ($this->migration()::ILLUSTRATIONS as $slug => $file) {
            $svg = (string) file_get_contents(resource_path('illustrations/formations/'.$slug.'.svg'));

            // Le titre est écrit par le code, jamais par l'image ; pas d'image intégrée (logo, photo, visage).
            $this->assertStringNotContainsString('<text', $svg);
            $this->assertStringNotContainsString('<image', $svg);
            $this->assertStringNotContainsString('<tspan', $svg);
            $this->assertDoesNotMatchRegularExpression('/openai|chatgpt|anthropic|claude|google|gemini|fcfa|octobre/i', strip_tags(preg_replace('/aria-label="[^"]*"/', '', $svg)));
        }
    }

    public function test_the_migration_fills_only_the_three_formations_without_an_image(): void
    {
        $migration = $this->migration();
        $formations = collect($migration::ILLUSTRATIONS)->map(fn (string $file, string $slug) => Formation::factory()->create(['slug' => $slug, 'image' => null]));
        $other = Formation::factory()->create(['slug' => 'automatisation-ia', 'image' => null]);

        $migration->up();
        $migration->up();

        foreach ($formations as $formation) {
            $this->assertSame($formation->slug.'.webp', $formation->fresh()->image);
            $this->assertStringContainsString('images/formations/'.$formation->slug.'.webp', $formation->fresh()->image_url);
        }
        $this->assertNull($other->fresh()->image);
    }

    public function test_an_image_uploaded_in_the_admin_is_never_replaced(): void
    {
        $formation = Formation::factory()->create(['slug' => 'creation-agents-ia', 'image' => 'formations/photo-de-joseph.jpg']);
        $legacy = Formation::factory()->create(['slug' => 'chatgpt-claude-initiation-ia', 'image' => 'ancienne.jpg']);

        $this->migration()->up();

        $this->assertSame('formations/photo-de-joseph.jpg', $formation->fresh()->image);
        $this->assertSame('ancienne.jpg', $legacy->fresh()->image);
    }

    public function test_the_illustration_shows_in_the_catalogue_the_page_the_checkout_and_the_share_preview(): void
    {
        $migration = $this->migration();
        $formation = Formation::factory()->create(['slug' => 'ebooks-livres-professionnels-ia', 'image' => null, 'is_active' => true]);
        $migration->up();
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
        $formation = Formation::factory()->create(['slug' => 'creation-agents-ia', 'image' => 'creation-agents-ia.webp']);
        $admin = User::factory()->admin()->create();
        $fields = [
            'title' => $formation->title,
            'description' => 'Description.',
            'price' => 25000,
            'category' => $formation->category->value,
        ];

        $this->actingAs($admin)->put(route('admin.formations.update', $formation), $fields + ['remove_image' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($formation->fresh()->image);

        $formation->update(['image' => 'creation-agents-ia.webp']);
        Storage::fake('public');
        $this->actingAs($admin)->put(route('admin.formations.update', $formation), $fields + ['image' => UploadedFile::fake()->image('nouvelle.jpg', 1600, 1000)])->assertSessionHasNoErrors();
        $this->assertStringStartsWith('formations/', (string) $formation->fresh()->image);

        $this->assertFileExists(public_path('images/formations/creation-agents-ia.webp'));
    }
}
