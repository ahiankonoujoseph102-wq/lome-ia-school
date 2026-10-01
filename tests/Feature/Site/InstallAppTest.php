<?php

namespace Tests\Feature\Site;

use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Events\FormationAccessGranted;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\User;
use App\Support\InstallApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * « Installer l'application » : manifeste installable sur Chrome Android, bannière (Chrome, Android) et lien du pied
 * de page (explication sur iPhone). Logique du navigateur : resources/js/install-app.js.
 */
class InstallAppTest extends TestCase
{
    use RefreshDatabase;

    private const string BANNER = 'data-test="install-banner"';

    public function test_the_manifest_meets_chrome_install_criteria(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->assertOk()->json();

        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/application', $manifest['start_url']);
        // Identité inchangée : les applications déjà installées reçoivent ce manifeste comme une mise à jour.
        $this->assertSame('/mon-espace?source=app', $manifest['id']);
        $this->assertArrayNotHasKey('prefer_related_applications', $manifest);
        $this->assertSame('#0b1030', $manifest['theme_color']);

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $file = public_path(ltrim((string) parse_url($icon['src'], PHP_URL_PATH), '/'));
            $this->assertFileExists($file);
            [$width, $height] = getimagesize($file);
            $this->assertSame($icon['sizes'], $width.'x'.$height, $icon['src']);
            $this->assertSame('image/png', $icon['type']);
            $sizes[$icon['purpose']][] = $icon['sizes'];
        }
        $this->assertContains('192x192', $sizes['any']);
        $this->assertContains('512x512', $sizes['any']);
        $this->assertContains('512x512', $sizes['maskable']);

        // Même couleur de thème dans la page ; aucune promesse de fonctionnement hors connexion.
        $this->get(route('home'))->assertSee('<meta name="theme-color" content="#0b1030">', false);
        $this->assertStringNotContainsStringIgnoringCase('hors connexion', json_encode($manifest, JSON_UNESCAPED_UNICODE));
    }

    public function test_the_installed_app_opens_the_home_page_for_visitors_and_my_space_for_students(): void
    {
        $this->get('/application')->assertRedirect(route('home'));
        $this->actingAs(User::factory()->create())->get('/application')->assertRedirect(route('dashboard'));
    }

    public function test_the_install_offer_is_kept_from_the_page_head(): void
    {
        $html = (string) $this->get(route('home'))->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $this->assertStringContainsString("addEventListener('beforeinstallprompt'", $head);
        $this->assertStringContainsString('event.preventDefault();', $head);
        $this->assertStringContainsString('window.lisInstallPrompt = event;', $head);
    }

    public function test_the_banner_is_on_public_and_student_pages(): void
    {
        $formation = Formation::factory()->create();

        foreach (['home', 'formations.index', 'consultation', 'about', 'contact', 'business', 'affiliate.program', 'legal.privacy'] as $route) {
            $this->assertBanner($this->get(route($route)), true, $route);
        }
        $this->assertBanner($this->get(route('formations.show', $formation)), true, 'formations.show')
            ->assertSee(InstallApp::BANNER_TEXT)
            ->assertSee('>Installer</button>', false)
            ->assertSee('aria-label="Fermer"', false);

        $student = User::factory()->create();
        foreach (['dashboard', 'student.formations.index', 'student.purchases.index', 'student.profile', 'student.notifications', 'student.affiliate.index', 'student.sessions.index', 'chat.index'] as $route) {
            $this->assertBanner($this->actingAs($student)->get(route($route)), true, $route);
        }
    }

    public function test_the_banner_never_appears_on_payment_login_course_or_admin_pages(): void
    {
        $formation = Formation::factory()->create();

        foreach (['login', 'register', 'password.request'] as $route) {
            $this->assertBanner($this->get(route($route)), false, $route);
        }
        $this->assertBanner($this->get(route('checkout.show', $formation)), false, 'checkout.show');

        $lesson = Lesson::factory()->preview()->create();
        $this->assertBanner($this->get(route('lessons.show', [$lesson->formation(), $lesson])), false, 'lessons.show');

        $student = User::factory()->create();
        FormationAccessGranted::dispatch($student->enroll($formation, EnrollmentSource::Purchase));
        $this->assertBanner($this->actingAs($student)->get(route('checkout.show', $formation)), false, 'checkout.show (élève)');
        $this->assertBanner($this->actingAs($student)->get(route('chat.show', $formation)), false, 'chat.show');

        $unconfirmed = User::factory()->create(['email_verified_at' => null]);
        $this->assertBanner($this->actingAs($unconfirmed)->get(route('email-confirmation.show')), false, 'email-confirmation.show');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        foreach (['admin.index', 'admin.payments.index', 'admin.formations.index'] as $route) {
            $this->assertBanner($this->actingAs($admin)->get(route($route)), false, $route);
        }
        $this->assertBanner($this->actingAs($admin)->get(route('lessons.show', [$lesson->formation(), $lesson])), false, 'lessons.show (admin)');

        // Liste fermée : les pages de paiement, de connexion et d'admin n'y sont pas.
        foreach (['checkout.show', 'student.sessions.payment', 'student.purchases.show', 'login', 'register', 'lessons.show', 'admin.index', 'moderation.reports.index'] as $route) {
            $this->assertFalse(InstallApp::bannerShownOn($route), $route);
        }
        $this->assertFalse(InstallApp::bannerShownOn('home', 'chat'));
    }

    public function test_the_banner_stays_above_the_price_bar_and_never_over_the_main_buttons(): void
    {
        $html = (string) $this->get(route('formations.show', Formation::factory()->create()))->getContent();

        // Barre de prix repérée (la bannière se place au-dessus) et protégée.
        $this->assertMatchesRegularExpression('/<div data-bottom-bar data-install-avoid class="fixed inset-x-0 bottom-0/', $html);
        $this->assertStringContainsString('btn-gold', $html);
        $this->assertStringContainsString(":class=\"covering && 'invisible'\"", $html);

        // Barre de navigation de l'espace élève.
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee('<nav data-bottom-bar', false);

        // Bouton d'Ama remonté au-dessus de la bannière ; bannière effacée quand la fenêtre d'Ama est ouverte.
        $css = (string) file_get_contents(resource_path('css/site.css'));
        $this->assertStringContainsString('.install-banner-shown .voice-assistant', $css);
        $this->assertStringContainsString('.ama-open .install-banner', $css);
        $this->assertStringContainsString('.install-banner-shown::after', $css);
    }

    public function test_the_footer_link_and_the_iphone_explanation(): void
    {
        $this->get(route('home'))
            ->assertSee('data-test="install-link"', false)
            ->assertSee("Installer l'application", false)
            ->assertSee(InstallApp::IOS_HELP);

        // Aussi sur les pages sans bannière qui ont un pied de page (connexion) et dans l'espace élève.
        $this->get(route('login'))->assertSee('data-test="install-link"', false);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee('data-test="install-link"', false);

        $this->assertSame('Touchez le bouton Partager, puis « Sur l\'écran d\'accueil »', InstallApp::IOS_HELP);
        $this->assertSame('Installez Lomé IA School sur votre téléphone pour y accéder en un toucher', InstallApp::BANNER_TEXT);
    }

    public function test_the_banner_is_stable_in_forced_dark_mode(): void
    {
        $view = (string) file_get_contents(resource_path('views/site/partials/install-banner.blade.php'))
            .file_get_contents(resource_path('views/site/partials/install-link.blade.php'));

        // Fond foncé, texte clair, contours en ombre : ni voile blanc, ni bordure, ni texte foncé sur fond clair.
        $this->assertStringNotContainsString('bg-white', $view);
        $this->assertStringNotContainsString('border', $view);
        $this->assertStringNotContainsString('text-night-9', $view);
        $this->assertStringContainsString('bg-night-900/95', $view);
        $this->assertStringContainsString('bg-gold-500/20 px-3.5 py-2 text-sm font-semibold text-white ring-1 ring-gold-500', $view);
    }

    /**
     * Logique du navigateur (resources/js/install-app.js) exécutée avec Node.js.
     */
    public function test_browser_logic_delay_dismissal_iphone_and_installation(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/install-app.js.');
        }

        $module = json_encode('file://'.resource_path('js/install-app.js'));
        $program = <<<JS
            const app = await import({$module});
            const memory = () => { const data = {}; return { getItem: (k) => data[k] ?? null, setItem: (k, v) => { data[k] = String(v); } }; };
            const broken = { getItem() { throw new Error('bloqué'); }, setItem() { throw new Error('bloqué'); } };
            const prompt = (outcome) => ({ calls: 0, prompt() { this.calls++; return Promise.resolve(); }, userChoice: Promise.resolve({ outcome }) });
            const out = {};
            let clock = 1_000_000;
            const now = () => clock;
            const day = 86_400_000;

            // Délai de 20 s cumulé sur la visite (deuxième page à 15 s : 5 s restantes).
            const session = memory();
            out.delays = [app.remainingDelay(session, clock), app.remainingDelay(session, clock + 15000), app.remainingDelay(session, clock + 25000), app.remainingDelay(broken, clock)];

            // Bannière : écran tactile + offre du navigateur + délai écoulé.
            const timers = [];
            const state = app.createInstallState({ touch: true, storage: memory(), session: memory(), now, schedule: (fn, ms) => timers.push([fn, ms]) }).start();
            out.timer = timers.map(([, ms]) => ms);
            out.beforeOffer = [state.bannerVisible, state.linkVisible];
            state.installable(prompt('accepted'));
            out.beforeDelay = state.bannerVisible;
            timers[0][0]();
            out.afterDelay = [state.bannerVisible, state.linkVisible];
            out.accepted = await state.install();
            out.afterInstall = [state.installed, state.bannerVisible, state.linkVisible];

            // Croix : plus de bannière pendant 30 jours (même après rechargement), le lien reste.
            const storage = memory();
            const ready = () => { const s = app.createInstallState({ touch: true, storage, session: memory(), now, schedule() {} }); s.delayDone = true; s.installable(prompt('accepted')); return s; };
            const first = ready();
            first.dismiss();
            out.dismissed = [first.bannerVisible, first.linkVisible];
            clock += 29 * day;
            out.after29Days = ready().bannerVisible;
            clock += 2 * day;
            out.after31Days = ready().bannerVisible;

            // « Annuler » dans la fenêtre du navigateur = fermeture ; une offre ne sert qu'une fois.
            const cancelled = ready();
            const offer = prompt('dismissed');
            cancelled.installable(offer);
            out.cancelled = [await cancelled.install(), offer.calls, cancelled.bannerVisible, cancelled.linkVisible, await cancelled.install()];

            // iPhone : jamais de bannière ; le lien ouvre l'explication.
            const iphone = app.createInstallState({ ios: true, touch: true, storage: memory(), session: memory(), now, schedule() {} });
            iphone.delayDone = true;
            out.iphone = [iphone.bannerVisible, iphone.linkVisible, await iphone.install(), iphone.helpOpen];
            out.iosDetection = [
                app.isIos({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)' }),
                app.isIos({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', platform: 'MacIntel', maxTouchPoints: 5 }),
                app.isIos({ userAgent: 'Mozilla/5.0 (Linux; Android 14; TECNO KJ5)', platform: 'Linux armv8l', maxTouchPoints: 5 }),
            ];

            // Déjà installée : ni bannière ni lien, même avec une offre.
            const installed = app.createInstallState({ installed: app.isInstalled({ standaloneDisplay: true }), touch: true, storage: memory(), session: memory(), now, schedule() {} });
            installed.delayDone = true;
            installed.installable(prompt('accepted'));
            out.installed = [installed.bannerVisible, installed.linkVisible];

            // Ordinateur (pas d'écran tactile) : pas de bannière, mais le lien installe.
            const desktop = ready();
            desktop.touch = false;
            out.desktop = [desktop.bannerVisible, desktop.linkVisible];

            // Stockage bloqué (navigation privée) : aucune erreur.
            const privateMode = app.createInstallState({ touch: true, storage: broken, session: broken, now, schedule() {} }).start();
            privateMode.installable(prompt('dismissed'));
            out.private = [privateMode.bannerVisible, await privateMode.install()];

            // Placement : au-dessus de la barre de prix ; chevauchement d'un bouton.
            out.bottom = [app.bottomOffset(800, []), app.bottomOffset(800, [728]), app.bottomOffset(800, [728, 900])];
            const box = { left: 12, right: 348, top: 640, bottom: 716 };
            out.overlaps = [
                app.overlaps(box, { left: 16, right: 344, top: 700, bottom: 752, width: 328, height: 52 }),
                app.overlaps(box, { left: 16, right: 344, top: 560, bottom: 612, width: 328, height: 52 }),
                app.overlaps(box, { left: 16, right: 344, top: 700, bottom: 700, width: 0, height: 0 }),
            ];

            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame([20000, 5000, 0, 20000], $out['delays']);
        $this->assertSame([20000], $out['timer']);
        $this->assertSame([false, false], $out['beforeOffer']);
        $this->assertFalse($out['beforeDelay']);
        $this->assertSame([true, true], $out['afterDelay']);
        $this->assertSame('accepted', $out['accepted']);
        $this->assertSame([true, false, false], $out['afterInstall']);

        $this->assertSame([false, true], $out['dismissed']);
        $this->assertFalse($out['after29Days']);
        $this->assertTrue($out['after31Days']);
        $this->assertSame(['dismissed', 1, false, false, 'none'], $out['cancelled']);

        $this->assertSame([false, true, 'help', true], $out['iphone']);
        $this->assertSame([true, true, false], $out['iosDetection']);
        $this->assertSame([false, false], $out['installed']);
        $this->assertSame([false, true], $out['desktop']);
        $this->assertSame([false, 'dismissed'], $out['private']);

        $this->assertSame([12, 84, 84], $out['bottom']);
        $this->assertSame([true, false, false], $out['overlaps']);
    }

    private function assertBanner(TestResponse $response, bool $expected, string $page): TestResponse
    {
        $response->assertOk();
        $this->assertSame($expected, str_contains((string) $response->getContent(), self::BANNER), $page);

        return $response;
    }
}
