<?php

namespace Tests\Feature\Packs;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\AdminTestCase;

/**
 * Affiche en haut de la page /accompagnement (téléversée dans l'admin) et affiche propre à chaque offre.
 */
class PackPosterTest extends AdminTestCase
{
    private Formation $pack;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Formation::query()->where('kind', 'pack')->update(['is_active' => true]);
        $this->pack = Formation::query()->where('slug', 'accompagnement-ia-niveau-1')->sole();
    }

    private function upload(int $width = 3000, int $height = 2000, string $name = 'affiche.jpg'): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.formations.packs-poster'), ['poster' => UploadedFile::fake()->image($name, $width, $height)])
            ->assertSessionHasNoErrors();
    }

    public function test_without_a_poster_the_page_is_unchanged(): void
    {
        $this->get(route('packs'))
            ->assertOk()
            ->assertDontSee('data-test="packs-hero-poster"', false)
            ->assertDontSee('data-test="packs-poster"', false)
            ->assertDontSee('data-test="pack-card-poster"', false)
            ->assertSee('Accompagnement IA');
    }

    public function test_the_admin_uploads_a_poster_shown_at_the_top_of_the_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.formations.index'))
            ->assertOk()
            ->assertSee('Affiche de la page des offres')
            ->assertSee('1600 × 1000')
            ->assertSee('data-test="packs-poster-none"', false);

        $this->upload();

        $path = (string) SiteSettings::get('site.packs_poster');
        $this->assertStringStartsWith('packs/', $path);
        Storage::disk('public')->assertExists($path);
        // Même recadrage que l'affiche d'une formation : 1600 × 1000.
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path)) ?: [0, 0];
        $this->assertSame([1600, 1000], [$width, $height]);
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::SettingsUpdated->value, 'description' => 'Affiche de la page des offres d\'accompagnement ajoutée.']);

        $response = $this->get(route('packs'))->assertOk();
        $response->assertSee('data-test="packs-hero-poster"', false)
            ->assertSee('data-test="packs-poster"', false)
            ->assertSee(Storage::disk('public')->url($path))
            // Mêmes classes que l'affiche de la page d'une formation.
            ->assertSee('aspect-[16/10] overflow-hidden rounded-3xl bg-night-800 shadow-2xl shadow-electric-900/50 ring-1 ring-veil/10', false)
            ->assertSee('order-1 lg:order-2', false);

        $this->actingAs($this->admin)->get(route('admin.formations.index'))->assertSee('data-test="packs-poster-current"', false)->assertSee('Retirer l\'affiche', false);
    }

    public function test_a_new_poster_replaces_and_deletes_the_old_one(): void
    {
        $this->upload();
        $old = (string) SiteSettings::get('site.packs_poster');

        $this->upload(1600, 1000, 'nouvelle.png');
        $new = (string) SiteSettings::get('site.packs_poster');

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Affiche de la page des offres d\'accompagnement remplacée.']);
    }

    public function test_the_admin_removes_the_poster(): void
    {
        $this->upload();
        $path = (string) SiteSettings::get('site.packs_poster');

        $this->actingAs($this->admin)->put(route('admin.formations.packs-poster'), ['remove_poster' => '1'])->assertSessionHasNoErrors();

        $this->assertEmpty(SiteSettings::get('site.packs_poster'));
        Storage::disk('public')->assertMissing($path);
        $this->get(route('packs'))->assertOk()->assertDontSee('data-test="packs-poster"', false);
    }

    public function test_wrong_files_are_refused(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.formations.packs-poster'), ['poster' => UploadedFile::fake()->create('affiche.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('poster');
        $this->actingAs($this->admin)
            ->put(route('admin.formations.packs-poster'), ['poster' => UploadedFile::fake()->image('enorme.jpg', 1600, 1000)->size(9000)])
            ->assertSessionHasErrors('poster');
        $this->actingAs($this->admin)->put(route('admin.formations.packs-poster'), [])->assertSessionHasErrors('poster');

        $this->assertNull(SiteSettings::get('site.packs_poster'));
    }

    public function test_a_missing_file_leaves_no_empty_space(): void
    {
        SiteSettings::save(['site.packs_poster' => 'packs/introuvable.jpg']);

        $this->get(route('packs'))->assertOk()->assertDontSee('data-test="packs-poster"', false);
    }

    public function test_a_pack_with_its_own_poster_shows_it_on_its_card(): void
    {
        Storage::disk('public')->put('formations/offre.jpg', 'image');
        $this->pack->update(['image' => 'formations/offre.jpg']);

        $html = (string) $this->get(route('packs'))->assertOk()->getContent();

        // Une seule carte a son affiche ; les autres restent telles quelles.
        $this->assertSame(1, substr_count($html, 'data-test="pack-card-poster"'));
        $this->assertStringContainsString(Storage::disk('public')->url('formations/offre.jpg'), $html);
    }

    public function test_only_the_admin_can_change_the_poster(): void
    {
        foreach ([User::factory()->create(['role' => UserRole::Moderator]), User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->put(route('admin.formations.packs-poster'), ['poster' => UploadedFile::fake()->image('affiche.jpg', 1600, 1000)])
                ->assertForbidden();
        }

        $this->assertNull(SiteSettings::get('site.packs_poster'));
    }
}
