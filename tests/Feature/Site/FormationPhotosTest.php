<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use App\Settings\SiteSettings;
use App\Support\FormationPhotos as Photos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photos d'illustration des formations et des offres (App\Support\FormationPhotos, public/images/photos/formations/,
 * scripts/build-formation-photos.sh, CREDITS-PHOTOS.md) : cartes du catalogue, grande photo en tête des pages
 * de formation et de la page des offres, priorité à l'image envoyée dans l'admin, visuel de secours.
 */
class FormationPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function formation(string $slug, array $attributes = []): Formation
    {
        Formation::query()->where('slug', $slug)->delete();

        return Formation::factory()->create(['slug' => $slug, 'image' => null, 'is_active' => true, ...$attributes]);
    }

    public function test_each_photo_exists_in_four_light_webp_sizes_at_16_by_10(): void
    {
        foreach (array_keys(Photos::PHOTOS) as $slug) {
            foreach (Photos::WIDTHS as $width) {
                $path = public_path(Photos::path($slug, $width));

                $this->assertFileExists($path);
                $info = getimagesize($path);
                $this->assertSame([$width, intdiv($width * 10, 16)], array_slice($info, 0, 2), $slug.' '.$width);
                $this->assertSame('image/webp', $info['mime']);
            }

            // Une carte sur téléphone charge 480 ou 800 px : léger sur une connexion mobile.
            $this->assertLessThan(25 * 1024, filesize(public_path(Photos::path($slug, 480))), $slug);
            $this->assertLessThan(60 * 1024, filesize(public_path(Photos::path($slug, 800))), $slug);
            $this->assertLessThan(150 * 1024, filesize(public_path(Photos::path($slug, 1600))), $slug);
        }
    }

    public function test_each_photo_is_different_and_matches_the_script_and_the_credits(): void
    {
        // Une seule photo partagée, volontairement : celle de l'ancienne offre niveau 3 (offres retirées du site en
        // novembre 2026) sert aussi à la formation complète.
        $photos = array_diff_key(Photos::PHOTOS, ['formation-complete-intelligence-artificielle' => true]);
        $this->assertSame(array_values($photos), array_values(array_unique($photos)));
        $this->assertSame(Photos::PHOTOS['accompagnement-ia-niveau-3'], Photos::PHOTOS['formation-complete-intelligence-artificielle']);

        preg_match_all('/^([a-z0-9-]+) (\d+) (center|north)$/m', (string) file_get_contents(base_path('scripts/build-formation-photos.sh')), $matches);
        $this->assertSame(Photos::PHOTOS, array_combine($matches[1], array_map('intval', $matches[2])));

        $credits = (string) file_get_contents(base_path('CREDITS-PHOTOS.md'));
        $this->assertStringContainsString('licence Pexels', $credits);
        foreach (Photos::PHOTOS as $slug => $id) {
            $this->assertStringContainsString('| '.$slug, $credits);
            $this->assertStringContainsString('https://www.pexels.com/photo/'.$id.'/', $credits);
        }
    }

    public function test_every_formation_and_offer_has_a_photo(): void
    {
        $slugs = Formation::query()->whereIn('kind', ['course', 'pack'])->pluck('slug')
            ->merge(['chatgpt-claude-initiation-ia', 'creation-agents-ia', 'e-commerce-produits-digitaux-ia']);

        foreach ($slugs as $slug) {
            $this->assertArrayHasKey($slug, Photos::PHOTOS);
        }
    }

    public function test_catalogue_cards_show_a_lazy_photo_with_the_site_veil(): void
    {
        $formation = $this->formation('automatisation-ia');

        $html = (string) $this->get(route('formations.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<img\s+src="[^"]*/images/photos/formations/automatisation-ia-800\.webp"\s+srcset="[^"]*automatisation-ia-480\.webp 480w, [^"]*automatisation-ia-1600\.webp 1600w"\s+sizes="[^"]+"\s+alt="Photo d&\#039;illustration"\s+class="cover-photo[^"]*"\s+loading="lazy"#', $html);
        $this->assertStringContainsString('cover-photo-veil', $html);
        $this->assertStringNotContainsString('alt="'.e($formation->title).'"', $html);
    }

    public function test_the_formation_page_opens_on_a_large_photo_with_the_title_and_the_button_over_it(): void
    {
        $formation = $this->formation('automatisation-ia', ['price' => 15000]);

        $html = (string) $this->get(route('formations.show', $formation))->assertOk()->getContent();

        $this->assertStringContainsString('data-test="formation-photo-hero"', $html);
        $this->assertMatchesRegularExpression('#<img\s+src="[^"]*automatisation-ia-800\.webp"[^>]*sizes="100vw"[^>]*alt="Photo d&\#039;illustration"[^>]*loading="eager"\s+fetchpriority="high"#', $html);
        $this->assertStringContainsString('photo-hero-veil', $html);
        // Titre puis bouton d'achat, posés sur la photo (même section) ; plus de visuel à droite.
        $hero = str($html)->after('data-test="formation-photo-hero"')->before('</section>');
        $this->assertStringContainsString('<h1', (string) $hero);
        $this->assertStringContainsString('Acheter maintenant', (string) $hero);
        $this->assertStringNotContainsString('data-test="formation-photo"', (string) $hero);
    }

    public function test_the_free_formation_page_also_opens_on_its_photo(): void
    {
        $formation = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        $formation->update(['is_active' => true]);

        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('data-test="formation-photo-hero"', false)
            ->assertSee('formation-gratuite-ia-800.webp', false);
    }

    public function test_an_image_uploaded_in_the_admin_always_wins_over_the_photo(): void
    {
        $formation = $this->formation('automatisation-ia', ['image' => 'formations/mon-affiche.jpg']);

        $this->assertNull(Photos::for($formation));
        $this->get(route('formations.show', $formation))->assertOk()
            ->assertDontSee('automatisation-ia-800.webp', false)
            ->assertDontSee('data-test="formation-photo-hero"', false)
            ->assertSee('mon-affiche.jpg', false);
    }

    public function test_the_photo_replaces_the_drawn_illustration_which_stays_as_a_fallback(): void
    {
        $withPhoto = $this->formation('automatisation-ia', ['image' => 'automatisation-ia.webp']);
        $this->assertNotNull(Photos::for($withPhoto));

        // Formation sans photo prévue : illustration dessinée, sinon visuel aux couleurs du site.
        $illustrated = $this->formation('nouvelle-formation', ['image' => 'automatisation-ia.webp']);
        $plain = $this->formation('autre-formation');

        $html = (string) $this->get(route('formations.index'))->assertOk()->getContent();
        $this->assertStringContainsString('images/formations/automatisation-ia.webp', $html);
        $this->assertStringContainsString('cover-art', $html);

        $this->get(route('formations.show', $illustrated))->assertOk()->assertDontSee('data-test="formation-photo-hero"', false);
        $this->get(route('formations.show', $plain))->assertOk()->assertSee('cover-art', false);
    }

    public function test_the_packs_page_shows_a_large_photo_and_a_photo_on_each_card(): void
    {
        Formation::query()->where('kind', 'pack')->update(['is_active' => true, 'registration_ends_at' => now()->addDays(2)]);

        $html = (string) $this->get(route('packs'))->assertOk()->getContent();

        $this->assertStringContainsString('data-test="formation-photo-hero-image"', $html);
        $this->assertStringContainsString('accompagnement-800.webp', $html);
        $this->assertSame(3, substr_count($html, 'data-test="pack-card-photo"'));
        foreach (['accompagnement-ia-niveau-1', 'accompagnement-ia-niveau-2', 'accompagnement-ia-niveau-3'] as $slug) {
            $this->assertStringContainsString($slug.'-800.webp', $html);
        }

        // Avec une affiche envoyée dans l'admin : l'affiche, sans la grande photo.
        Storage::fake('public');
        Storage::disk('public')->put('packs/affiche.jpg', 'image');
        SiteSettings::save(['site.packs_poster' => 'packs/affiche.jpg']);
        $this->get(route('packs'))->assertOk()->assertDontSee('data-test="formation-photo-hero-image"', false);
    }

    public function test_animations_only_run_when_the_phone_accepts_them(): void
    {
        $css = (string) file_get_contents(resource_path('css/site.css'));
        $block = str($css)->after('.photo-hero-veil {')->before('/* Titre en dégradé');

        $this->assertStringContainsString('@media (prefers-reduced-motion: no-preference) {', (string) $block);
        $motion = str((string) $block)->after('@media (prefers-reduced-motion: no-preference) {');
        $this->assertStringContainsString('transform: scale(1.05)', (string) $motion);
        $this->assertStringContainsString('animation: photo-hero-drift', (string) $motion);
        $this->assertStringNotContainsString('transition', (string) str((string) $block)->before('@media'));
    }
}
