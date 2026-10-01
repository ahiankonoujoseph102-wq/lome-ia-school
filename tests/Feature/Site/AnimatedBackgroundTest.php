<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\User;
use App\Support\AnimatedBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fond animé de l'accueil et du haut du catalogue : CSS seulement (transform et opacité), léger,
 * coupé si « moins d'animations » ou « économie de données », jamais sur les pages de travail.
 */
class AnimatedBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private const string MARKER = 'data-test="animated-background"';

    private function css(): string
    {
        $css = (string) file_get_contents(resource_path('css/site.css'));
        $start = strpos($css, " * Fond animé de l'accueil");
        $end = strpos($css, ' * Apparition douce au défilement');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($css, $start, $end - $start);
    }

    private function toggle(bool $enabled, ?User $admin = null): void
    {
        $admin ??= User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'fond-anime'), ['enabled' => $enabled ? '1' : '0'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.edit', 'fond-anime'));
        auth()->logout();
    }

    public function test_it_is_on_by_default_on_the_home_page_and_the_top_of_the_catalogue(): void
    {
        $this->assertTrue(AnimatedBackground::enabled());

        $home = $this->get(route('home'))->assertOk()->assertSee(self::MARKER, false)->getContent();
        $this->assertSame(1, substr_count($home, self::MARKER));
        // L'ancien fond fixe du haut de page est remplacé, pas empilé.
        $this->assertStringNotContainsString('class="glow pointer-events-none absolute inset-0 -z-10"', $home);

        $this->get(route('formations.index'))->assertOk()->assertSee(self::MARKER, false);
    }

    public function test_it_never_shows_on_working_pages(): void
    {
        $formation = Formation::factory()->create();
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach ([route('login'), route('register'), route('formations.show', $formation), route('consultation'), route('business')] as $url) {
            $this->get($url)->assertOk()->assertDontSee(self::MARKER, false);
        }

        foreach ([route('checkout.show', $formation), route('dashboard'), route('chat.index')] as $url) {
            $this->actingAs($student)->get($url)->assertOk()->assertDontSee(self::MARKER, false);
        }

        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertDontSee(self::MARKER, false);

        $this->assertSame(['home', 'formations.index'], AnimatedBackground::PAGES);
        $this->assertFalse(AnimatedBackground::shownOn('checkout.show'));
        $this->assertFalse(AnimatedBackground::shownOn(null));
    }

    public function test_the_admin_switch_turns_it_off_without_a_new_release_and_brings_back_the_still_background(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Fond animé');
        $this->actingAs($admin)->get(route('admin.settings.edit', 'fond-anime'))->assertOk()
            ->assertSee('data-test="animated-background-toggle"', false)
            ->assertSee('name="enabled"', false)
            ->assertSee('checked', false);

        $this->toggle(false, $admin);

        $this->assertFalse(AnimatedBackground::enabled());
        $this->get(route('home'))->assertOk()->assertDontSee(self::MARKER, false)->assertSee('class="glow pointer-events-none absolute inset-0 -z-10"', false);
        $this->get(route('formations.index'))->assertOk()->assertDontSee(self::MARKER, false)->assertSee('class="glow pointer-events-none absolute inset-0 -z-10 opacity-70"', false);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'like', 'Fond animé désactivé%')->exists());

        $this->toggle(true, $admin);
        $this->get(route('home'))->assertSee(self::MARKER, false);
        $this->assertTrue(AdminActivity::query()->where('description', 'like', 'Fond animé activé%')->exists());
    }

    public function test_only_the_admin_can_change_it(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->get(route('admin.settings.edit', 'fond-anime'))->assertForbidden();
        $this->actingAs($moderator)->put(route('admin.settings.update', 'fond-anime'), ['enabled' => '0'])->assertForbidden();
        $this->assertTrue(AnimatedBackground::enabled());
    }

    public function test_save_data_visitors_get_still_patterns(): void
    {
        $this->get(route('home'), ['Save-Data' => 'on'])->assertOk()->assertSee('data-still', false);
        $this->get(route('home'))->assertOk()->assertDontSee('data-still', false);
    }

    public function test_only_transform_and_opacity_are_animated_and_motion_preferences_are_respected(): void
    {
        $css = $this->css();

        preg_match_all('/@keyframes\s+(anim-[a-z-]+)\s*\{((?:[^{}]*\{[^{}]*\})+)\s*\}/', $css, $keyframes, PREG_SET_ORDER);
        $this->assertCount(4, $keyframes);

        foreach ($keyframes as [, $name, $body]) {
            preg_match_all('/([a-z-]+)\s*:/', $body, $properties);
            $this->assertEmpty(array_diff($properties[1], ['transform', 'opacity']), $name.' ne doit animer que transform et opacité.');
        }

        // Moins d'animations, économie de données, pause hors de l'écran ou onglet masqué.
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('.anim-bg[data-still] *', $css);
        $this->assertStringContainsString('animation-play-state: paused', $css);

        // Aucune image ni vidéo de fond : uniquement des masques SVG intégrés.
        preg_match_all('/url\(([^)]*)\)/', $css, $urls);
        $this->assertNotEmpty($urls[1]);
        foreach ($urls[1] as $url) {
            $this->assertStringStartsWith('"data:image/svg+xml,', $url);
        }

        // Ni filtre de flou animé, ni voile blanc que Chrome recolorerait.
        $this->assertStringNotContainsString('filter', $css);
        $this->assertStringNotContainsString('255 255 255', $css);
    }

    public function test_the_weight_stays_under_five_kilobytes_compressed(): void
    {
        $added = $this->css()
            .file_get_contents(resource_path('js/animated-background.js'))
            .file_get_contents(resource_path('views/site/partials/animated-background.blade.php'));

        $this->assertLessThan(5 * 1024, strlen((string) gzencode($added, 9)));

        // Aucune nouvelle bibliothèque : le module n'importe rien.
        $this->assertStringNotContainsString('import ', (string) file_get_contents(resource_path('js/animated-background.js')));
    }

    public function test_the_icons_are_generic(): void
    {
        $this->assertSame(['robot', 'chat', 'book', 'gear', 'phone', 'bolt'], AnimatedBackground::ICONS);

        $partial = (string) file_get_contents(resource_path('views/site/partials/animated-background.blade.php'));
        foreach (AnimatedBackground::ICONS as $icon) {
            $this->assertStringContainsString("['".$icon."'", $partial);
            $this->assertStringContainsString('.anim-icon-'.$icon, $this->css());
        }

        $this->assertDoesNotMatchRegularExpression('/openai|google|anthropic|tiktok|facebook|youtube|whatsapp/i', $this->css().$partial);
        $this->get(route('home'))->assertSee('aria-hidden="true"', false);
    }

    /**
     * Logique du navigateur (resources/js/animated-background.js) exécutée avec Node.js et un faux document.
     */
    public function test_browser_logic_pauses_off_screen_and_respects_preferences(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/animated-background.js.');
        }

        $module = json_encode('file://'.resource_path('js/animated-background.js'));
        $program = <<<JS
            import * as bg from {$module};

            function fakePage({ reduced = false, saveData = false } = {}) {
                const attrs = new Set();
                const layer = { dataset: { depth: '0.5' }, style: {} };
                const root = {
                    offsetHeight: 600,
                    setAttribute: (name) => attrs.add(name),
                    toggleAttribute: (name, on) => (on ? attrs.add(name) : attrs.delete(name)),
                    querySelectorAll: () => [layer],
                };
                const listeners = {};
                let observer;
                const doc = { hidden: false, querySelectorAll: () => [root], addEventListener: (type, fn) => (listeners['doc:' + type] = fn) };
                const win = {
                    scrollY: 0,
                    navigator: { connection: { saveData } },
                    matchMedia: () => ({ matches: reduced }),
                    requestAnimationFrame: (fn) => fn(),
                    addEventListener: (type, fn) => (listeners[type] = fn),
                    IntersectionObserver: class { constructor(cb) { observer = (visible) => cb([{ target: root, isIntersecting: visible }]); } observe() {} },
                };
                const api = bg.initAnimatedBackgrounds(doc, win);

                return { api, attrs, layer, listeners, doc, win, see: (visible) => observer && observer(visible) };
            }

            const reduced = fakePage({ reduced: true });
            const saving = fakePage({ saveData: true });

            const page = fakePage();
            const pausedBefore = page.attrs.has('data-paused');
            page.see(true);
            const runningOnScreen = !page.attrs.has('data-paused');
            page.win.scrollY = 200;
            page.listeners.scroll();
            const shifted = page.layer.style.transform;
            page.doc.hidden = true;
            page.listeners['doc:visibilitychange']();
            const pausedHiddenTab = page.attrs.has('data-paused');
            page.doc.hidden = false;
            page.see(false);
            const pausedOffScreen = page.attrs.has('data-paused');
            page.win.scrollY = 5000;
            page.listeners.scroll();
            const frozenOffScreen = page.layer.style.transform;

            console.log(JSON.stringify({
                allowed: bg.motionAllowed({}),
                notReduced: bg.motionAllowed({ reducedMotion: true }),
                notSaving: bg.motionAllowed({ saveData: true }),
                offset: bg.layerOffset(200, 0.5, 600),
                capped: bg.layerOffset(5000, 0.5, 600),
                negative: bg.layerOffset(-50, 0.5, 600),
                reducedStill: reduced.attrs.has('data-still') && reduced.api === null && !reduced.listeners.scroll,
                savingStill: saving.attrs.has('data-still') && saving.api === null,
                pausedBefore, runningOnScreen, shifted, pausedHiddenTab, pausedOffScreen, frozenOffScreen,
            }));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertTrue($out['allowed']);
        $this->assertFalse($out['notReduced']);
        $this->assertFalse($out['notSaving']);
        $this->assertSame(100, $out['offset']);
        $this->assertSame(300, $out['capped']);
        $this->assertSame(0, $out['negative']);
        $this->assertTrue($out['reducedStill']);
        $this->assertTrue($out['savingStill']);
        $this->assertTrue($out['pausedBefore']);
        $this->assertTrue($out['runningOnScreen']);
        $this->assertSame('translate3d(0, 100px, 0)', $out['shifted']);
        $this->assertTrue($out['pausedHiddenTab']);
        $this->assertTrue($out['pausedOffScreen']);
        $this->assertSame('translate3d(0, 100px, 0)', $out['frozenOffScreen']);
    }
}
