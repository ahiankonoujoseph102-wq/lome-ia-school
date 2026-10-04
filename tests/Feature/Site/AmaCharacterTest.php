<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\AmaFace;
use App\Support\VoiceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Visage animé d'Ama et bulle de présentation : visage sur toutes les pages d'Ama, interrupteurs « Personnage animé » et
 * « Ama se présente toute seule » (allumés par défaut ; éteints, tout redevient comme avant), messages écrits à l'avance
 * et modifiables dans l'admin (aucun appel à Gemini), grand personnage du mode appel, légèreté, stabilité face au mode
 * sombre forcé et réglage « moins d'animations ». Logique du navigateur : AmaPresenceTest (Node.js).
 */
class AmaCharacterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.gemini.key' => 'cle-de-test', 'services.gemini.model' => 'modele-de-test']);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values = []): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', ...$values]]);
    }

    /**
     * Réglages envoyés au composant du navigateur (attribut x-data de la page).
     *
     * @return array<string, mixed>
     */
    private function browserConfig(TestResponse $response): array
    {
        $html = (string) $response->assertOk()->getContent();
        $this->assertSame(1, preg_match("/x-data=\"voiceAssistant\\(JSON\\.parse\\('(.*?)'\\)\\)\"/", $html, $match));

        return json_decode((string) json_decode('"'.$match[1].'"'), true);
    }

    public function test_the_switches_are_on_by_default(): void
    {
        $this->settings();

        $this->assertTrue(VoiceAssistant::characterEnabled());
        $this->assertTrue(VoiceAssistant::nudgeEnabled());
        $this->assertSame('a', VoiceAssistant::characterStyle());
    }

    public function test_the_face_replaces_the_round_button_on_every_page_of_ama(): void
    {
        $this->settings();
        $formation = Formation::factory()->create();
        $student = User::factory()->create();

        foreach ([route('home'), route('formations.index'), route('formations.show', $formation), route('about'), route('contact'), route('login'), route('register')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('data-test="ama-face"', false)
                ->assertSee('class="ama-face ama-face-zoom"', false)
                ->assertSee('aria-label="Parler avec notre assistante"', false);
        }

        // Paiement : le bloc du montant et du numéro à envoyer n'est jamais recouvert par la bulle de présentation.
        $this->actingAs($student)->get(route('checkout.show', $formation))->assertOk()->assertSee('data-test="ama-face"', false)->assertSee('data-ama-avoid', false);
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('data-test="ama-face"', false);
    }

    public function test_the_call_shows_the_large_character_with_its_four_states(): void
    {
        $this->settings();
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-test="ama-face-call"', $html);
        $this->assertStringContainsString('ama-face-large', $html);
        $this->assertStringContainsString('x-bind:data-state="callState"', $html);
        $this->assertStringContainsString('x-bind:data-talking="faceTalking"', $html);
        $this->assertStringContainsString('x-bind:data-enter="faceEnter"', $html);
        // Tracé d'entrée (contour doré) et bulle « réfléchit » seulement dans la grande version.
        $this->assertSame(1, substr_count($html, 'class="ama-ink"'));
        $this->assertSame(1, substr_count($html, 'class="ama-dots"'));
        // L'en-tête de la discussion montre aussi le visage, sa bouche suit la lecture à voix haute.
        $this->assertStringContainsString('data-test="ama-face-header"', $html);
        // Repos : écran « Appel terminé » ; le cercle de l'appel garde un contour fixe au repos.
        $this->assertStringContainsString('x-bind:data-state="\'idle\'"', $html);
        $this->assertStringContainsString("callState === 'idle'", $html);
    }

    public function test_switched_off_the_previous_button_and_call_circle_come_back(): void
    {
        $this->settings(['character' => false, 'nudge' => false]);
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('ama-face', $html);
        $this->assertStringNotContainsString('data-test="ama-nudge"', $html);
        $this->assertStringContainsString('aria-hidden="true">A</span>', $html);
        $this->assertStringContainsString('ama-call-bars', $html);
        $this->assertStringContainsString('class="relative grid size-40 place-items-center', $html);

        $config = $this->browserConfig($this->get(route('home')));
        $this->assertFalse($config['character']);
        $this->assertNull($config['nudge']);
    }

    public function test_the_elevenlabs_agent_keeps_its_previous_button(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'elevenlabs', 'agent_id' => VoiceAssistant::EXAMPLE_ID]]);

        $this->get(route('home'))->assertOk()->assertDontSee('ama-face', false);
    }

    public function test_the_presentation_bubble_is_adapted_to_each_kind_of_page(): void
    {
        $this->settings();
        $formation = Formation::factory()->create(['price' => 15000]);
        $free = Formation::factory()->create(['price' => 0]);
        $student = User::factory()->create();

        $expected = [
            'home' => [route('home'), 'Bonjour, je suis Ama, votre assistante. Une question ? Je suis là pour vous aider.'],
            'formation' => [route('formations.show', $formation), 'Une question sur cette formation ? Posez-la-moi, je vous réponds.'],
            'free' => [route('formations.show', $free), 'Envie de vous inscrire ? Je peux vous aider à le faire.'],
            'other' => [route('about'), 'Une question ? Je suis là pour vous répondre.'],
        ];

        foreach ($expected as $type => [$url, $text]) {
            $config = $this->browserConfig($this->get($url));
            $this->assertSame(['text' => $text, 'type' => $type, 'seconds' => 8], $config['nudge'], $type);
        }

        $checkout = $this->browserConfig($this->actingAs($student)->get(route('checkout.show', $formation)));
        $this->assertSame('Besoin d\'aide pour payer ? Je vous guide étape par étape.', $checkout['nudge']['text']);
        $this->assertSame('checkout', $checkout['nudge']['type']);

        // Pages de compte et espace élève : aucune bulle (le visage reste).
        $this->assertNull($this->browserConfig($this->actingAs($student)->get(route('dashboard')))['nudge']);
        auth()->logout();
        $this->assertNull($this->browserConfig($this->get(route('login')))['nudge']);
    }

    public function test_the_texts_promise_nothing(): void
    {
        foreach (VoiceAssistant::NUDGES as $text) {
            $this->assertLessThanOrEqual(VoiceAssistant::NUDGE_MAX_LENGTH, mb_strlen($text));
            $this->assertDoesNotMatchRegularExpression('/\d|garanti|gagner|revenu|argent|certifi|diplôme|places? limitée|dernière|vite/iu', $text);
        }
    }

    public function test_showing_the_face_and_the_bubble_never_calls_gemini(): void
    {
        $this->settings();
        Http::fake();

        $this->get(route('home'))->assertOk()->assertSee('data-test="ama-nudge"', false);
        $this->get(route('formations.show', Formation::factory()->create()))->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseCount('assistant_usage', 0);
        $this->assertDatabaseCount('assistant_conversations', 0);
    }

    public function test_the_bubble_is_closed_by_a_gesture_and_points_to_the_chat_only(): void
    {
        $this->settings();
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<div\s+x-show="nudge"(.*?)data-test="ama-nudge"\s*>(.*?)<\/div>/s', $html, $match));
        $this->assertStringContainsString('@pointerdown="swipeStart($event)"', $match[1]);
        $this->assertStringContainsString('@pointerup.window="swipeEnd($event)"', $match[1]);
        // Invisible tant qu'elle passerait au-dessus d'un bouton important, ou au-dessus du visage s'il y a la place.
        $this->assertStringContainsString("covering && 'invisible'", $match[1]);
        $this->assertStringContainsString('data-test="ama-nudge-close"', $match[2]);
        // Toucher la bulle ouvre seulement la fenêtre d'Ama (jamais le micro ni l'appel).
        $this->assertStringContainsString('@click="open()"', $match[2]);
        $this->assertStringNotContainsString('startCall', $match[2]);
        $this->assertStringNotContainsString('listen', $match[2]);
    }

    public function test_the_admin_sets_the_switches_the_face_and_the_texts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->settings();

        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))
            ->assertOk()
            ->assertSee('Personnage animé')
            ->assertSee('Ama se présente toute seule')
            ->assertSee('data-test="assistant-face-style"', false)
            ->assertSee('Cheveux naturels')
            ->assertSee('Tresses')
            ->assertSee('Foulard')
            ->assertSee('Besoin d&#039;aide pour payer ? Je vous guide étape par étape.', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1', 'provider' => 'gemini', 'character' => '1', 'nudge' => '1', 'character_style' => 'c',
            'nudges' => ['home' => '  Bonjour ! Je   suis Ama.  ', 'checkout' => VoiceAssistant::NUDGES['checkout'], 'formation' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame('c', VoiceAssistant::characterStyle());
        $this->assertSame('Bonjour ! Je suis Ama.', VoiceAssistant::nudgeText('home'));
        // Champ vidé ou identique au texte d'origine : texte d'origine (rien d'enregistré).
        $this->assertSame(VoiceAssistant::NUDGES['formation'], VoiceAssistant::nudgeText('formation'));
        $this->assertSame(['home' => 'Bonjour ! Je suis Ama.'], config('site.voice_assistant.nudges'));
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'like', '%personnage animé : oui (visage c), Ama se présente toute seule : oui%')->exists());

        auth()->logout();
        $this->get(route('home'))->assertOk()->assertSee('data-style="c"', false);
        $this->assertSame('Bonjour ! Je suis Ama.', $this->browserConfig($this->get(route('home')))['nudge']['text']);

        // Éteints : enregistrés comme tels.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'provider' => 'gemini', 'character' => '0', 'nudge' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse(VoiceAssistant::characterEnabled());
        $this->assertFalse(VoiceAssistant::nudgeEnabled());

        // Message trop long, visage inconnu : refusés.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'provider' => 'gemini', 'character_style' => 'z', 'nudges' => ['home' => str_repeat('a', VoiceAssistant::NUDGE_MAX_LENGTH + 1)]])
            ->assertSessionHasErrors(['character_style', 'nudges.home']);
    }

    public function test_the_face_is_light_and_drawn_without_any_image_or_library(): void
    {
        $this->settings();
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        // Masques et couleurs écrits une seule fois par page, même avec quatre visages affichés.
        $this->assertSame(1, substr_count($html, '.ama-face[data-style="a"] .ama-l-skin{'));
        $this->assertSame(4, substr_count($html, 'class="ama-face'));
        foreach (['a', 'b', 'c'] as $style) {
            $this->assertLessThan(7000, strlen(AmaFace::css($style)));
        }

        // Ajout à la page compressée (gzip) : moins de 4 Ko, aucune image, aucune vidéo, aucun script en plus.
        $this->settings(['character' => false, 'nudge' => false]);
        $before = (string) $this->get(route('home'))->getContent();
        $this->assertLessThan(4096, strlen((string) gzencode($html, 9)) - strlen((string) gzencode($before, 9)));
        $this->assertStringNotContainsString('<video', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+ama/i', $html);
    }

    public function test_the_colours_stay_stable_with_chrome_forced_dark_mode(): void
    {
        foreach (['a', 'b', 'c'] as $style) {
            $css = AmaFace::css($style);
            // Fonds de couleur découpés par des masques : jamais un SVG coloré (recoloré par Chrome).
            $this->assertStringContainsString('--m:url("data:image/svg+xml,', $css);
            preg_match_all('/background:(#[0-9a-f]{6})/', $css, $colors);
            $this->assertNotEmpty($colors[1]);

            // Aucune couleur plus lumineuse que le reflet des yeux (#d4c9b8, mesuré stable ; #ddd2c2 était assombri).
            foreach ($colors[1] as $color) {
                [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
                $this->assertLessThanOrEqual(203, 0.2126 * $r + 0.7152 * $g + 0.0722 * $b, $style.' '.$color);
            }
        }
    }

    public function test_the_motion_respects_reduced_motion_and_animates_only_transform_and_opacity(): void
    {
        $css = (string) file_get_contents(resource_path('css/site.css'));
        $start = strpos($css, " * Visage d'Ama (composant x-site.ama-face)");
        $this->assertNotFalse($start);
        $block = substr($css, $start);

        // Toutes les animations sont dans « prefers-reduced-motion: no-preference » et coupées par data-still.
        $motion = substr($block, (int) strpos($block, '@media (prefers-reduced-motion: no-preference)'));
        $motion = substr($motion, 0, (int) strpos($motion, '@keyframes'));
        $this->assertSame(0, preg_match('/animation:|transition:/', substr($block, 0, (int) strpos($block, '@media (prefers-reduced-motion'))));
        preg_match_all('/^\s*(\.ama-face[^{,\n]*)/m', $motion, $selectors);
        $this->assertGreaterThanOrEqual(10, count($selectors[1]));
        foreach ($selectors[1] as $selector) {
            $this->assertStringContainsString(':not([data-still])', $selector);
        }

        // Images clés : transform et opacité seulement (le tracé d'entrée anime un trait, une seule fois).
        preg_match_all('/@keyframes (ama-[a-z-]+) \{(.*?)\n\}/s', $block, $frames, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(7, count($frames));
        foreach ($frames as [, $name, $body]) {
            preg_match_all('/([a-z-]+):/', $body, $properties);
            $allowed = $name === 'ama-draw' ? ['stroke-dashoffset'] : ['transform', 'opacity'];
            $this->assertSame([], array_values(array_diff(array_unique($properties[1]), $allowed)), $name);
        }
    }
}
