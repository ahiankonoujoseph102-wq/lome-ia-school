<?php

namespace Tests\Feature\Site;

use App\Models\AdminActivity;
use App\Models\User;
use App\Support\HomeVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Image d'aperçu de la vidéo d'accueil : envoyée depuis l'admin (recadrée en 9:16, WebP léger), sinon
 * miniature YouTube montrée entière (jamais recadrée sur les côtés) sur un fond flouté de la même image.
 */
class HomeVideoPosterTest extends TestCase
{
    use RefreshDatabase;

    private const string ID = 'dQw4w9WgXcQ';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function save(array $overrides = []): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'video-accueil'), array_replace([
            'enabled' => '1',
            'video_url' => 'https://www.youtube.com/watch?v='.self::ID,
            'title' => '',
            'subtitle' => '',
            'tiktok_url' => '',
        ], $overrides))->assertSessionHasNoErrors();
        auth()->logout();
    }

    public function test_the_admin_screen_has_the_poster_field(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'video-accueil'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('data-test="home-video-poster-field"', false)
            ->assertSee('Image d\'aperçu', false)
            ->assertSee('name="poster"', false)
            ->assertDontSee('data-test="home-video-poster-remove"', false);
    }

    public function test_an_uploaded_poster_is_cropped_to_9_16_in_light_webp_and_replaces_the_youtube_thumbnail(): void
    {
        // Photo horizontale : elle est recadrée au centre en vertical.
        $this->save(['poster' => UploadedFile::fake()->image('apercu.jpg', 1920, 1080)]);

        $path = HomeVideo::posterPath();
        $this->assertNotNull($path);
        $this->assertStringStartsWith('site/home-video/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        $contents = Storage::disk('public')->get($path);
        $size = getimagesizefromstring((string) $contents);
        $this->assertSame([540, 960], [$size[0], $size[1]]);
        $this->assertSame('image/webp', $size['mime']);
        $this->assertLessThan(20 * 1024, strlen((string) $contents));

        $html = $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-video-poster"', false)
            ->assertSee(Storage::disk('public')->url($path), false)
            ->getContent();

        // La miniature YouTube n'est plus demandée, et le lecteur ne se charge toujours qu'au toucher.
        $this->assertStringNotContainsString('i.ytimg.com', $html);
        $this->assertStringContainsString('<template x-if="playing">', $html);

        $this->assertTrue(AdminActivity::query()->where('description', 'like', '%nouvelle image d\'aperçu%')->exists());
    }

    public function test_saving_again_keeps_the_poster_and_removing_it_brings_back_the_thumbnail(): void
    {
        $this->save(['poster' => UploadedFile::fake()->image('apercu.png', 1080, 1920)]);
        $path = HomeVideo::posterPath();

        $this->save(['title' => 'Nouveau titre']);
        $this->assertSame($path, HomeVideo::posterPath());
        $this->get(route('home'))->assertSee('data-test="home-video-poster"', false);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'video-accueil'))
            ->assertSee('data-test="home-video-poster-remove"', false)
            ->assertSee('data-test="home-video-poster-current"', false);
        auth()->logout();

        $this->save(['remove_poster' => '1']);
        $this->assertNull(HomeVideo::posterPath());
        Storage::disk('public')->assertMissing((string) $path);
        $this->get(route('home'))->assertDontSee('data-test="home-video-poster"', false)->assertSee('data-test="home-video-thumbnail"', false);
    }

    public function test_a_new_poster_replaces_and_deletes_the_old_one(): void
    {
        $this->save(['poster' => UploadedFile::fake()->image('a.jpg', 1080, 1920)]);
        $old = (string) HomeVideo::posterPath();

        $this->save(['poster' => UploadedFile::fake()->image('b.jpg', 1080, 1920)]);

        $this->assertNotSame($old, HomeVideo::posterPath());
        Storage::disk('public')->assertMissing($old);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'video-accueil'), [
            'enabled' => '1',
            'video_url' => 'https://www.youtube.com/watch?v='.self::ID,
            'poster' => UploadedFile::fake()->create('apercu.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors(['poster' => 'L\'image d\'aperçu doit être une image (JPEG, PNG ou WebP).']);

        $this->assertNull(HomeVideo::posterPath());
    }

    public function test_without_poster_the_thumbnail_is_shown_whole_on_a_blurred_background(): void
    {
        $this->save();

        $html = $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-video-backdrop"', false)
            ->assertSee('data-test="home-video-thumbnail"', false)
            ->getContent();

        // Une seule adresse d'image (le navigateur ne la télécharge qu'une fois) : fond flouté + miniature en 16:9.
        $this->assertSame(2, substr_count($html, 'https://i.ytimg.com/vi/'.self::ID.'/hqdefault.jpg'));

        $partial = (string) file_get_contents(resource_path('views/site/partials/home-video.blade.php'));
        $this->assertStringContainsString('blur-xl', $partial);
        // La miniature tient toute la largeur du cadre (rien n'est coupé sur les côtés) et les bandes noires du 4:3 sont retirées.
        $this->assertStringContainsString('aspect-video w-full overflow-hidden', $partial);
        $this->assertSame(1, substr_count($partial, 'x-on:error="fail()"'));
    }
}
