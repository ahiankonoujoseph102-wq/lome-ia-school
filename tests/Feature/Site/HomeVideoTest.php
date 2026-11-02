<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\User;
use App\Support\HomeVideo;
use Database\Seeders\FormationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Vidéo d'accueil (verticale, YouTube, chargée au toucher) et ses réglages dans l'admin.
 * Aucun lien n'est écrit dans le code : les identifiants ci-dessous ne servent qu'aux essais.
 */
class HomeVideoTest extends TestCase
{
    use RefreshDatabase;

    private const string ID = 'dQw4w9WgXcQ';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_replace([
            'enabled' => '1',
            'video_url' => 'https://www.youtube.com/watch?v='.self::ID,
            'title' => '',
            'subtitle' => '',
            'tiktok_url' => '',
        ], $overrides);
    }

    /**
     * Section de l'accueil et lecteur commun (x-site.lazy-youtube), qu'elle utilise pour le cadre de la vidéo.
     */
    private function partial(): string
    {
        return file_get_contents(resource_path('views/site/partials/home-video.blade.php'))
            .file_get_contents(resource_path('views/components/site/lazy-youtube.blade.php'));
    }

    private function save(array $overrides = []): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'video-accueil'), $this->form($overrides))->assertSessionHasNoErrors();
    }

    public function test_no_link_is_written_in_the_code_and_the_section_is_hidden_by_default(): void
    {
        $this->assertSame('', config('site.home_video.video_url'));
        $this->assertSame('', config('site.home_video.tiktok_url'));
        $this->assertFalse(HomeVideo::visible());

        $this->get(route('home'))->assertOk()
            ->assertDontSee('data-test="home-video"', false)
            ->assertDontSee('youtube-nocookie')
            ->assertDontSee('ytimg.com');
    }

    public function test_default_texts(): void
    {
        $this->assertSame('L\'intelligence artificielle expliquée simplement', HomeVideo::title());
        $this->assertSame('Une courte vidéo de Coach Joseph', HomeVideo::subtitle());
    }

    public function test_the_admin_screen_has_the_four_fields_and_the_switch(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Vidéo d\'accueil');
        $this->actingAs($admin)->get(route('admin.settings.edit', 'video-accueil'))->assertOk()
            ->assertSee('Afficher la vidéo')
            ->assertSee('name="video_url"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="subtitle"', false)
            ->assertSee('name="tiktok_url"', false)
            ->assertSee('data-test="home-video-toggle"', false)
            ->assertSee('data-test="home-video-hidden-note"', false)
            ->assertDontSee('type="url"', false)
            ->assertDontSee('name="transcript"', false);
    }

    public function test_the_video_shows_on_the_home_page_with_a_thumbnail_and_no_player_before_the_touch(): void
    {
        $this->save();

        $this->assertTrue(HomeVideo::visible());

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-video"', false)
            ->assertSee('L\'intelligence artificielle expliquée simplement')
            ->assertSee('Une courte vidéo de Coach Joseph')
            ->assertSee('https://i.ytimg.com/vi/'.self::ID.'/hqdefault.jpg', false)
            ->assertSee('data-test="home-video-play"', false)
            ->assertSee('aspect-[9/16]', false)
            ->assertSee('Cette vidéo ne peut pas être lue ici pour le moment.')
            ->assertSee('Regarder sur YouTube');

        // Le lecteur est ajouté seulement au toucher (modèle Alpine), jamais en lecture automatique, en youtube-nocookie.com.
        $html = $response->getContent();
        $this->assertStringContainsString('<template x-if="playing">', $html);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/'.self::ID.'?autoplay=1', $html);
        $this->assertStringNotContainsString('https://www.youtube.com/embed/', $html);
        $this->assertStringNotContainsString('<iframe class', $html);
        $this->assertStringNotContainsString('muted', $html);

        // Aucune transcription ni sous-titre.
        $this->assertStringNotContainsStringIgnoringCase('transcription', $html);
        $this->assertStringNotContainsStringIgnoringCase('cc_load_policy', $html);

        // Sans JavaScript, le bouton lecture reste un lien vers YouTube.
        $this->assertStringContainsString('href="https://www.youtube.com/watch?v='.self::ID.'"', $html);
    }

    public function test_the_player_is_never_outside_a_touch_template(): void
    {
        $this->save();

        $partial = $this->partial();

        // L'unique <iframe> est dans le modèle « playing » (rien n'est chargé avant le toucher).
        $this->assertSame(1, substr_count($partial, '<iframe'));
        $this->assertLessThan(strpos($partial, '<iframe'), strpos($partial, '<template x-if="playing">'));
        $this->assertStringContainsString('referrerpolicy="strict-origin-when-cross-origin"', $partial);
    }

    public function test_all_three_youtube_link_forms_are_accepted(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v='.self::ID,
            'https://youtu.be/'.self::ID.'?si=abc',
            'https://www.youtube.com/shorts/'.self::ID,
            'HTTPS://WWW.YOUTUBE.COM/shorts/'.self::ID,
            'Regardez : https://youtu.be/'.self::ID."\u{00A0}",
        ] as $link) {
            $this->save(['video_url' => $link]);

            $this->assertSame(self::ID, HomeVideo::videoId(), $link);
            $this->get(route('home'))->assertSee('https://i.ytimg.com/vi/'.self::ID.'/hqdefault.jpg', false);
        }
    }

    public function test_an_invalid_link_is_refused_and_a_stored_invalid_link_never_breaks_the_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'video-accueil'), $this->form(['video_url' => 'https://vimeo.com/123']))
            ->assertSessionHasErrors('video_url');

        // Lien invalide déjà enregistré (ancienne valeur, saisie hors écran) : la section disparaît, rien ne casse.
        config(['site.home_video.video_url' => 'https://exemple.com/pas-youtube']);
        $this->assertFalse(HomeVideo::visible());
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="home-video"', false);
    }

    public function test_an_empty_link_hides_the_section(): void
    {
        $this->save();
        $this->get(route('home'))->assertSee('data-test="home-video"', false);

        $this->save(['video_url' => '']);
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="home-video"', false);
    }

    public function test_the_switch_hides_the_video_without_losing_the_settings(): void
    {
        $this->save(['enabled' => '0']);

        $this->assertSame('https://www.youtube.com/watch?v='.self::ID, config('site.home_video.video_url'));
        $this->assertFalse(HomeVideo::visible());
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="home-video"', false);

        $this->save(['enabled' => '1']);
        $this->get(route('home'))->assertSee('data-test="home-video"', false);
    }

    public function test_title_and_subtitle_are_editable_and_come_back_when_emptied(): void
    {
        $this->save(['title' => 'Mon titre de vidéo', 'subtitle' => 'Ma phrase courte']);

        $this->get(route('home'))->assertSee('Mon titre de vidéo')->assertSee('Ma phrase courte');

        $this->save(['title' => '  ', 'subtitle' => '']);

        $this->get(route('home'))
            ->assertSee('L\'intelligence artificielle expliquée simplement')
            ->assertSee('Une courte vidéo de Coach Joseph')
            ->assertDontSee('Mon titre de vidéo');
    }

    public function test_the_tiktok_button_only_shows_when_its_link_is_set(): void
    {
        $this->save();
        $this->get(route('home'))->assertDontSee('data-test="home-video-tiktok"', false)->assertDontSee('Suivre sur TikTok');

        $this->save(['tiktok_url' => 'Mon compte : HTTPS://WWW.TIKTOK.COM/@coachjoseph']);
        $this->assertSame('https://www.tiktok.com/@coachjoseph', HomeVideo::tiktokUrl());

        $this->get(route('home'))
            ->assertSee('data-test="home-video-tiktok"', false)
            ->assertSee('href="https://www.tiktok.com/@coachjoseph"', false)
            ->assertSee('Suivre sur TikTok');
    }

    public function test_a_tiktok_link_that_is_not_tiktok_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['https://exemple.com/@x', 'https://tiktok.com.evil.test/@x', 'javascript:alert(1)'] as $link) {
            $this->actingAs($admin)->put(route('admin.settings.update', 'video-accueil'), $this->form(['tiktok_url' => $link]))
                ->assertSessionHasErrors('tiktok_url');
        }

        $this->save(['tiktok_url' => 'https://vm.tiktok.com/ZMabcdef/']);
        $this->assertSame('https://vm.tiktok.com/ZMabcdef/', HomeVideo::tiktokUrl());
    }

    public function test_the_formations_button_goes_down_to_the_catalogue(): void
    {
        $this->seed(FormationSeeder::class);
        $this->save();

        $this->get(route('home'))
            ->assertSee('data-test="home-video-formations"', false)
            ->assertSee('href="#catalogue"', false)
            ->assertSee('id="catalogue"', false)
            ->assertSee('Voir les formations');
    }

    public function test_the_section_sits_between_the_top_of_the_page_and_the_catalogue_and_after_the_live_banner(): void
    {
        $this->seed(FormationSeeder::class);
        $this->save();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $hero = strpos($html, 'class="eyebrow"');
        $video = strpos($html, 'data-test="home-video"');
        $catalogue = strpos($html, 'id="catalogue"');

        $this->assertNotFalse($hero);
        $this->assertNotFalse($video);
        $this->assertNotFalse($catalogue);
        $this->assertLessThan($video, $hero);
        $this->assertLessThan($catalogue, $video);

        // Le bandeau « En direct » (tant que l'édition n'est pas terminée) reste avant la vidéo.
        $this->travelTo(Carbon::parse('2026-10-03 12:00', 'Africa/Lome'));
        $html = $this->get(route('home'))->getContent();
        $banner = strpos($html, 'data-test="home-live-offer"');

        $this->assertNotFalse($banner);
        $this->assertLessThan(strpos($html, 'data-test="home-video"'), $banner);
    }

    public function test_the_section_is_short_on_phones_and_the_frame_widens_only_when_the_visitor_plays(): void
    {
        $partial = $this->partial();

        // Avec une image d'aperçu 9:16 envoyée : aperçu étroit (10 rem) ; section compacte, titre et boutons réduits.
        $this->assertStringContainsString('mx-auto w-40', $partial);
        $this->assertStringContainsString('aspect-[9/16]', $partial);
        $this->assertStringContainsString('!py-6', $partial);
        $this->assertStringContainsString('text-xl', $partial);
        $this->assertStringContainsString('!px-2.5 !py-3 !text-sm', $partial);

        // Le cadre s'agrandit au toucher (lecture) ou si la vidéo est illisible, pas avant.
        $this->assertStringContainsString('data-[open=true]:w-[min(78%,17rem)]', $partial);
        $this->assertStringContainsString("x-bind:data-open=\"(playing || failed) ? 'true' : 'false'\"", $partial);
        $this->assertStringContainsString('motion-reduce:transition-none', $partial);

        // Dès les écrans moyens : 20 rem comme avant.
        $this->assertStringContainsString('sm:w-80', $partial);

        // Sans image d'aperçu (novembre 2026) : la miniature YouTube 16:9 en grand, puis le cadre passe en 9:16 au toucher.
        $this->save();
        $this->get(route('home'))->assertSee('data-test="home-video-frame"', false)->assertSee('data-video-mode="grow"', false);
    }

    public function test_a_broken_video_shows_a_message_and_a_youtube_link_instead_of_an_empty_area(): void
    {
        $this->save();

        $partial = $this->partial();

        $this->assertStringContainsString('<template x-if="failed">', $partial);
        $this->assertStringContainsString('x-on:error="thumbnailError($el)"', $partial);
        $this->assertStringContainsString('Regarder sur YouTube', $partial);

        $this->get(route('home'))->assertSee('data-test="home-video-fallback"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_the_style_does_not_depend_on_forced_dark_mode(): void
    {
        $partial = $this->partial();

        $this->assertStringNotContainsString('dark:', $partial);
        $this->assertStringNotContainsString('bg-white/', $partial);
        $this->assertDoesNotMatchRegularExpression('/\b(?:border|ring)-veil|bg-veil|text-gold-(?:200|300)/', $partial);
        $this->assertStringContainsString('bg-night-900', $partial);
        $this->assertStringContainsString('text-white', $partial);
    }

    public function test_the_privacy_policy_mentions_youtube_and_the_date_is_up_to_date(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('data-test="privacy-youtube"', false)
            ->assertSee('Au toucher du bouton lecture, la vidéo est fournie par YouTube (Google).', false)
            ->assertSee('youtube-nocookie.com')
            ->assertSee('Dernière mise à jour : 1er octobre 2026');
    }

    public function test_the_change_is_logged_and_only_the_admin_can_edit_it(): void
    {
        $this->save();

        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'like', '%Vidéo d\'accueil%')->exists());

        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $this->actingAs($moderator)->get(route('admin.settings.edit', 'video-accueil'))->assertForbidden();
        $this->actingAs($moderator)->put(route('admin.settings.update', 'video-accueil'), $this->form())->assertForbidden();
    }

    public function test_the_site_has_no_global_content_security_policy_to_adapt(): void
    {
        $this->save();

        $response = $this->get(route('home'))->assertOk();

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
        $this->assertStringNotContainsString('Content-Security-Policy', $response->getContent());
    }

    /**
     * Logique du navigateur (resources/js/home-video.js) exécutée avec Node.js.
     */
    public function test_browser_logic_detects_an_unplayable_video(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/home-video.js.');
        }

        $module = json_encode('file://'.resource_path('js/home-video.js'));
        $program = <<<JS
            import * as video from {$module};
            const origin = video.PLAYER_ORIGIN;
            console.log(JSON.stringify({
                origin,
                listening: JSON.parse(video.listeningMessage()),
                error: video.isPlaybackError(origin, JSON.stringify({ event: 'onError', info: 150 })),
                privateVideo: video.isPlaybackError(origin, JSON.stringify({ event: 'onError', info: 100 })),
                state: video.isPlaybackError(origin, JSON.stringify({ event: 'onStateChange', info: 1 })),
                other: video.isPlaybackError('https://exemple.com', JSON.stringify({ event: 'onError', info: 150 })),
                broken: video.isPlaybackError(origin, 'pas du json'),
                notString: video.isPlaybackError(origin, { event: 'onError' }),
            }));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame('https://www.youtube-nocookie.com', $out['origin']);
        $this->assertSame('listening', $out['listening']['event']);
        $this->assertTrue($out['error']);
        $this->assertTrue($out['privateVideo']);
        $this->assertFalse($out['state']);
        $this->assertFalse($out['other']);
        $this->assertFalse($out['broken']);
        $this->assertFalse($out['notString']);
    }
}
