<?php

namespace Tests\Feature\Site;

use App\Models\Testimonial;
use App\Models\User;
use App\Support\Testimonials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Témoignages vidéo (novembre 2026) : rangée qui défile sur le côté, carte au format de la vidéo, miniatures
 * nettes (format d'origine pour une vidéo verticale, haute définition pour une vidéo horizontale), lecteur au toucher.
 */
class VideoTestimonialsTest extends TestCase
{
    use RefreshDatabase;

    private const string ID = 'dQw4w9WgXcQ';

    private function video(array $attributes = []): Testimonial
    {
        return Testimonial::query()->create([
            'type' => 'video',
            'name' => 'Afi',
            'youtube_url' => 'https://youtu.be/'.self::ID,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    public function test_the_format_is_chosen_in_the_admin_or_guessed(): void
    {
        $this->assertSame('auto', Testimonials::videoFormat('https://youtu.be/'.self::ID, null));
        $this->assertSame('vertical', Testimonials::videoFormat('https://www.youtube.com/shorts/'.self::ID, null));
        $this->assertSame('horizontal', Testimonials::videoFormat('https://www.youtube.com/shorts/'.self::ID, 'horizontal'));
        $this->assertSame('vertical', Testimonials::videoFormat('https://youtu.be/'.self::ID, 'vertical'));
    }

    public function test_videos_are_shown_in_a_strip_with_sharp_thumbnails_and_the_player_only_after_a_tap(): void
    {
        $this->video();
        $this->video(['name' => 'Kodjo', 'video_format' => 'vertical']);
        $this->video(['name' => 'Ama', 'video_format' => 'horizontal']);
        Testimonial::query()->create(['type' => 'text', 'name' => 'Esso', 'text' => 'Texte', 'is_active' => true]);

        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-test="video-testimonials"', $html);
        $this->assertSame(3, substr_count($html, 'data-test="video-testimonial-thumbnail"'));
        $this->assertStringContainsString('data-video-format="auto"', $html);
        $this->assertStringContainsString('data-video-format="vertical"', $html);
        $this->assertStringContainsString('data-video-format="horizontal"', $html);
        // Vertical : miniature au format d'origine ; horizontal : haute définition ; autres tailles en repli.
        $this->assertStringContainsString('src="https://i.ytimg.com/vi/'.self::ID.'/oardefault.jpg"', $html);
        $this->assertStringContainsString('src="https://i.ytimg.com/vi/'.self::ID.'/maxresdefault.jpg"', $html);
        $this->assertStringContainsString('<template x-if="playing">', $html);
        // Le témoignage écrit reste en colonnes.
        $this->assertStringContainsString('Esso', $html);
    }

    public function test_the_admin_saves_the_video_format(): void
    {
        $admin = User::factory()->admin()->create();
        $testimonial = $this->video();

        $this->actingAs($admin)->get(route('admin.testimonials.edit', $testimonial))->assertOk()->assertSee('Format de la vidéo');

        $this->actingAs($admin)->put(route('admin.testimonials.update', $testimonial), [
            'type' => 'video',
            'name' => 'Afi',
            'youtube_url' => 'https://youtu.be/'.self::ID,
            'video_format' => 'vertical',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('vertical', $testimonial->refresh()->video_format);

        $this->actingAs($admin)->put(route('admin.testimonials.update', $testimonial), [
            'type' => 'video',
            'youtube_url' => 'https://youtu.be/'.self::ID,
            'video_format' => 'carre',
            'is_active' => '1',
        ])->assertSessionHasErrors('video_format');
    }
}
