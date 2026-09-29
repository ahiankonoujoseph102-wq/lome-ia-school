<?php

namespace Tests\Feature\Site;

use App\Http\Controllers\Site\SitemapController;
use App\Models\Formation;
use App\Models\User;
use App\Support\FormationShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bouton « Partager cette formation » : lien de la page publique, message WhatsApp (nom + lien, rien d'autre),
 * partage Facebook, aperçu (Open Graph) avec des adresses absolues fondées sur APP_URL, même avec NOINDEX=true.
 */
class FormationShareTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/cache/og'));

        parent::tearDown();
    }

    public function test_guests_and_students_see_the_share_button_with_three_choices(): void
    {
        $formation = Formation::factory()->create(['slug' => 'automatisation-ia', 'title' => 'Automatisation avec l\'IA']);
        $url = route('formations.show', $formation);

        foreach ([null, User::factory()->create()] as $user) {
            if ($user) {
                $this->actingAs($user);
            }

            $this->get($url)
                ->assertOk()
                ->assertSee('data-test="share-formation"', false)
                ->assertSee('Partager cette formation')
                ->assertSee('Copier le lien')
                ->assertSee('Lien copié')
                ->assertSee('https://wa.me/?text='.rawurlencode("Automatisation avec l'IA\n".$url), false)
                ->assertSee('https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url), false)
                ->assertSee('value="'.$url.'"', false);
        }
    }

    public function test_whatsapp_message_contains_only_the_title_and_the_link(): void
    {
        $formation = Formation::factory()->create(['slug' => 'agents-ia', 'title' => 'Création d\'agents IA']);

        $this->assertSame("Création d'agents IA\n".route('formations.show', $formation), FormationShare::message($formation));
    }

    public function test_shared_link_opens_the_public_page_with_price_and_buy_button(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);

        $this->get(FormationShare::url($formation))
            ->assertOk()
            ->assertSee($formation->title)
            ->assertSee($formation->formatted_price)
            ->assertSee('Acheter maintenant')
            ->assertSee(route('checkout.show', $formation), false);
    }

    public function test_links_and_preview_tags_use_app_url(): void
    {
        config(['app.url' => 'https://lomeiaschool.example']);
        $formation = Formation::factory()->create(['slug' => 'automatisation-ia', 'subtitle' => 'Apprenez à automatiser vos tâches']);

        $this->assertSame('https://lomeiaschool.example/formations/automatisation-ia', FormationShare::url($formation));

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('<meta property="og:url" content="https://lomeiaschool.example/formations/automatisation-ia">', false)
            ->assertSee('<meta property="og:image" content="https://lomeiaschool.example/og/formations/automatisation-ia.png?v=', false)
            ->assertSee('<meta property="og:image:type" content="image/png">', false)
            ->assertSee('<meta property="og:title" content="'.e($formation->title).'">', false)
            ->assertSee('<meta property="og:description" content="Apprenez à automatiser vos tâches">', false)
            ->assertSee('value="https://lomeiaschool.example/formations/automatisation-ia"', false);
    }

    public function test_preview_uses_the_formation_image_cropped_to_1200_by_630(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('photo.jpg', 1600, 1000)->store('formations', 'public');
        $formation = Formation::factory()->create(['image' => $path]);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.route('og.formation.photo', $formation).'?v=', false)
            ->assertSee('<meta property="og:image:type" content="image/jpeg">', false);

        $response = $this->get(route('og.formation.photo', $formation))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname()) ?: [0, 0];
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_missing_formation_image_falls_back_to_the_generated_preview(): void
    {
        Storage::fake('public');
        $formation = Formation::factory()->create(['image' => 'formations/introuvable.jpg']);

        $this->get(route('og.formation.photo', $formation))->assertRedirect(route('og.formation', $formation));
        $this->get(route('og.formation.photo', Formation::factory()->inactive()->create()))->assertNotFound();
    }

    public function test_noindex_blocks_search_engines_but_not_share_previews(): void
    {
        config(['app.noindex' => true]);
        $formation = Formation::factory()->create();

        $robots = (string) $this->get(route('robots'))->assertOk()->getContent();
        $this->assertStringContainsString("User-agent: facebookexternalhit\n", $robots);
        $this->assertStringContainsString("User-agent: WhatsApp\n", $robots);
        $this->assertMatchesRegularExpression('/User-agent: TelegramBot\nAllow: \/\n\nUser-agent: \*\nDisallow: \//', $robots);
        $this->assertCount(7, SitemapController::PREVIEW_BOTS);

        // Robots d'aperçu : page et image accessibles sans compte, balises présentes.
        foreach (['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'WhatsApp/2.23.20.0'] as $agent) {
            $this->withHeader('User-Agent', $agent)
                ->get(route('formations.show', $formation))
                ->assertOk()
                ->assertSee('<meta property="og:image"', false)
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

            $this->withHeader('User-Agent', $agent)->get(route('og.formation', $formation))->assertOk();
        }
    }

    public function test_admin_list_has_a_copy_link_button_for_visible_formations(): void
    {
        $admin = User::factory()->admin()->create();
        $visible = Formation::factory()->create(['title' => 'Formation visible']);
        Formation::factory()->inactive()->create(['title' => 'Formation masquée']);

        $this->actingAs($admin)
            ->get(route('admin.formations.index'))
            ->assertOk()
            ->assertSee('data-test="copy-formation-link"', false)
            ->assertSee("copyText('".str_replace('/', '\\/', route('formations.show', $visible))."')", false)
            ->assertSee('Copier le lien')
            ->assertSee('Lien inactif (masquée)');
    }
}
