<?php

namespace Tests\Feature\Packs;

use App\Models\Formation;
use App\Models\User;
use App\Packs\PackDeadline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Compte à rebours vivant des offres d'accompagnement : jours, heures, minutes et secondes, calculés à partir de
 * la date fixe enregistrée (la même pour tout le monde), horloge du téléphone corrigée par l'heure du serveur
 * (/heure-serveur), « Inscriptions closes » à zéro sans recharger la page, rien d'annoncé à chaque seconde aux
 * lecteurs d'écran, aucun effet si le téléphone demande moins d'animations.
 * Toutes les heures sont fixées dans le test : rien ne dépend du jour où il tourne.
 */
class PackCountdownTest extends TestCase
{
    use RefreshDatabase;

    private const string DEADLINE = '2026-10-12 16:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Formation::query()->where('kind', 'pack')->update([
            'is_active' => true,
            'registration_ends_at' => Carbon::parse(self::DEADLINE, 'Africa/Lome'),
        ]);
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse($time, 'Africa/Lome'));
    }

    private function pack(): Formation
    {
        return Formation::query()->where('slug', 'accompagnement-ia-niveau-1')->sole();
    }

    public function test_before_the_date_every_countdown_shows_days_hours_minutes_and_seconds(): void
    {
        $this->at('2026-10-10 13:27:18');

        $html = (string) $this->get(route('packs'))->assertOk()->getContent();

        // En-tête : 2 jours, 2 heures, 32 minutes et 42 secondes jusqu'au lundi 12 octobre 2026 à 16 h.
        $this->assertStringContainsString('Inscriptions ouvertes jusqu\'au lundi 12 octobre 2026 à 16 h', $html);
        $this->assertMatchesRegularExpression('#data-test="pack-countdown-days">2<.*?data-test="pack-countdown-hours">02<.*?data-test="pack-countdown-minutes">32<.*?data-test="pack-countdown-seconds">42<#s', $html);
        $this->assertStringContainsString('secondes', $html);

        // Barre fixe du bas.
        $this->assertMatchesRegularExpression('#data-test="packs-sticky-time"><span x-text="days">2</span>&nbsp;j <span x-text="pad\(hours\)">02</span>&nbsp;h <span x-text="pad\(minutes\)">32</span>&nbsp;min <span x-text="pad\(seconds\)"[^>]*>42</span>&nbsp;s</p>#', $html);

        // Date fixe du serveur et heure du serveur données au navigateur.
        $deadlineMs = Carbon::parse(self::DEADLINE, 'Africa/Lome')->getTimestampMs();
        $this->assertStringContainsString('\\u0022deadline\\u0022:'.$deadlineMs, $html);
        $this->assertStringContainsString('\\u0022serverNow\\u0022:'.now()->getTimestampMs(), $html);

        // Page de l'offre (récapitulatif de commande) : même compte à rebours, à la seconde.
        $this->actingAs(User::factory()->create())->get(route('checkout.show', $this->pack()))
            ->assertOk()
            ->assertSeeInOrder(['data-test="pack-countdown-days">2<', 'data-test="pack-countdown-hours">02<', 'data-test="pack-countdown-minutes">32<', 'data-test="pack-countdown-seconds">42<'], false);
    }

    public function test_the_countdown_is_the_same_for_every_visitor_and_never_restarts(): void
    {
        $this->at('2026-10-12 15:59:05');

        foreach ([null, User::factory()->create(), User::factory()->create()] as $visitor) {
            $request = $visitor ? $this->actingAs($visitor) : $this;
            $request->get(route('packs'))
                ->assertSeeInOrder(['data-test="pack-countdown-days">0<', 'data-test="pack-countdown-hours">00<', 'data-test="pack-countdown-minutes">00<', 'data-test="pack-countdown-seconds">55<'], false);
        }

        // Une minute plus tard pour tout le monde : il suit l'heure et ne repart jamais de zéro.
        $this->at('2026-10-12 15:59:50');
        $this->get(route('packs'))->assertSee('data-test="pack-countdown-seconds">10<', false);
    }

    public function test_after_the_date_the_page_shows_closed_registrations_and_no_countdown(): void
    {
        $this->at('2026-10-12 16:00:00');

        $html = (string) $this->get(route('packs'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-test="pack-countdown"', $html);
        $this->assertStringNotContainsString('data-test="packs-sticky-bar"', $html);
        $this->assertStringNotContainsString('data-test="pack-choose"', $html);
        $this->assertStringContainsString('data-test="pack-closed-notice"', $html);
        $this->assertStringContainsString('data-test="packs-closed"', $html);

        $this->at('2026-10-20 09:00:00');
        $this->get(route('packs'))->assertDontSee('data-test="pack-countdown"', false)->assertSee('Inscriptions closes');
    }

    public function test_screen_readers_get_a_minute_level_text_and_no_live_announcement(): void
    {
        $this->at('2026-10-11 14:58:30');

        $html = (string) $this->get(route('packs'))->assertOk()->getContent();

        $this->assertStringContainsString('data-test="pack-countdown-spoken">Temps restant : 1 jour, 1 heure et 1 minute<', $html);
        $this->assertMatchesRegularExpression('#data-test="pack-countdown" aria-hidden="true"#', $html);
        $this->assertMatchesRegularExpression('#aria-hidden="true" data-test="packs-sticky-time"#', $html);
        $this->assertStringNotContainsString('aria-live="polite"', $html);
        $this->assertStringNotContainsString('aria-live="assertive"', $html);
        $this->assertStringNotContainsString('role="timer"', $html);

        $this->assertSame('Temps restant : 2 heures et 0 minute', PackDeadline::spokenRemaining(['days' => 0, 'hours' => 2, 'minutes' => 0, 'closed' => false]));
        $this->assertSame('Temps restant : 3 minutes', PackDeadline::spokenRemaining(['days' => 0, 'hours' => 0, 'minutes' => 3, 'closed' => false]));
        $this->assertSame('Inscriptions closes', PackDeadline::spokenRemaining(['days' => 0, 'hours' => 0, 'minutes' => 0, 'closed' => true]));
    }

    public function test_the_server_time_is_given_without_cache(): void
    {
        $this->at('2026-10-12 10:00:00');

        $this->assertSame('/heure-serveur', route('server-time', absolute: false));

        $response = $this->getJson(route('server-time'))->assertOk()->assertExactJson(['now' => now()->getTimestampMs()]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
    }

    public function test_the_seconds_fade_only_when_the_phone_allows_animations(): void
    {
        $css = (string) file_get_contents(resource_path('css/site.css'));
        $start = strpos($css, '@media (prefers-reduced-motion: no-preference) {'."\n".'    .countdown-second');
        $this->assertNotFalse($start);

        $block = substr($css, $start);
        $this->assertStringContainsString('@keyframes countdown-tick-a', $block);
        // Seulement l'opacité, jamais de déplacement.
        preg_match_all('/@keyframes countdown-tick-[ab] \{(.*?)\n    \}/s', $block, $frames);
        $this->assertCount(2, $frames[1]);
        foreach ($frames[1] as $frame) {
            $this->assertMatchesRegularExpression('/^\s*from \{\s*opacity: [0-9.]+;\s*\}\s*$/', $frame);
        }

        // Aucune règle .countdown-second hors de ce bloc.
        $this->assertSame(2, substr_count($css, '.countdown-second.'));
    }

    public function test_the_browser_countdown_ticks_every_second_corrects_the_clock_and_closes_at_zero(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/countdown.js.');
        }

        $module = json_encode('file://'.resource_path('js/countdown.js'));
        $deadline = Carbon::parse(self::DEADLINE, 'Africa/Lome')->getTimestampMs();
        $program = <<<JS
            const c = await import({$module});
            const deadline = {$deadline};
            const out = {};
            out.parts = [
                c.countdownParts(deadline, deadline - (2 * 86400 + 2 * 3600 + 32 * 60 + 42) * 1000),
                c.countdownParts(deadline, deadline - 999),
                c.countdownParts(deadline, deadline - 1000),
                c.countdownParts(deadline, deadline - 1001),
                c.countdownParts(deadline, deadline),
                c.countdownParts(deadline, deadline + 5000),
            ];
            out.delays = [c.nextTickDelay(deadline, deadline - 10250), c.nextTickDelay(deadline, deadline - 10000), c.nextTickDelay(deadline, deadline - 10990)];
            out.labels = [
                c.countdownLabel(c.countdownParts(deadline, deadline - (86400 + 3600 + 90) * 1000)),
                c.countdownLabel(c.countdownParts(deadline, deadline - 150 * 1000)),
                c.countdownLabel(c.countdownParts(deadline, deadline)),
            ];
            out.measured = [c.measuredOffset(1000500, 2000000, 2000200), c.measuredOffset(5, 0, 20000), c.measuredOffset(Number.NaN, 0, 10)];

            // Téléphone en avance de 3 heures, page arrivée 2 s après sa préparation, puis heure demandée au serveur.
            let phone = deadline - 3600000 + 3 * 3600000;
            const server = () => phone - 3 * 3600000;
            const pageServerNow = server() - 2000;
            const calls = [];
            const clock = c.createServerClock({
                url: '/heure-serveur',
                now: () => phone,
                fetcher: async (url, options) => {
                    calls.push([url, options.cache]);
                    const answer = server() + 100;
                    phone += 200;

                    return { ok: true, json: async () => ({ now: answer }) };
                },
            });
            clock.hint(pageServerNow);
            out.hinted = c.countdownParts(deadline, clock.now());
            let changes = 0;
            clock.onChange(() => changes++);
            out.synced = await clock.sync();
            out.afterSync = c.countdownParts(deadline, clock.now());
            out.measuredFlag = clock.isMeasured();
            out.changes = changes;
            out.skipped = await clock.sync();
            out.calls = calls;

            // Réseau en panne : l'heure écrite dans la page reste la référence.
            const broken = c.createServerClock({ url: '/heure-serveur', now: () => 1000, fetcher: async () => { throw new Error('hors ligne'); } });
            broken.hint(4000);
            out.broken = [await broken.sync(), broken.offset()];

            // Arrivée à zéro : « closed » vrai à l'instant exact de la date.
            const closing = c.createServerClock({ now: () => deadline - 1 });
            closing.hint(deadline - 1);
            out.closing = [c.countdownParts(deadline, closing.now()).closed, c.countdownParts(deadline, closing.now() + 1).closed];

            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame([
            ['days' => 2, 'hours' => 2, 'minutes' => 32, 'seconds' => 42, 'closed' => false],
            ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 1, 'closed' => false],
            ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 1, 'closed' => false],
            ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 2, 'closed' => false],
            ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'closed' => true],
            ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'closed' => true],
        ], $out['parts']);
        // Prochain affichage juste après le changement de seconde, jamais plus d'une seconde.
        $this->assertSame([255, 1000, 995], $out['delays']);
        $this->assertSame(['Temps restant : 1 jour, 1 heure et 1 minute', 'Temps restant : 2 minutes', 'Inscriptions closes'], $out['labels']);
        $this->assertSame([-999600, null, null], $out['measured']);

        // Avec l'heure de la page seule : 2 s de trop (temps d'arrivée de la page) ; corrigé par l'heure du serveur.
        $this->assertSame(['days' => 0, 'hours' => 1, 'minutes' => 0, 'seconds' => 2, 'closed' => false], $out['hinted']);
        $this->assertTrue($out['synced']);
        $this->assertSame(['days' => 0, 'hours' => 1, 'minutes' => 0, 'seconds' => 0, 'closed' => false], $out['afterSync']);
        $this->assertTrue($out['measuredFlag']);
        $this->assertSame(1, $out['changes']);
        // Pas de nouvelle demande dans la minute.
        $this->assertFalse($out['skipped']);
        $this->assertSame([['/heure-serveur', 'no-store']], $out['calls']);
        $this->assertSame([false, 3000], $out['broken']);
        $this->assertSame([false, true], $out['closing']);
    }

    public function test_the_page_closes_itself_at_zero_without_reloading(): void
    {
        $js = (string) file_get_contents(resource_path('js/site.js'));
        $component = substr($js, (int) strpos($js, "Alpine.data('packCountdown'"), 2500);

        // Une mise à jour par seconde, réglée sur le changement de seconde.
        $this->assertStringContainsString('setTimeout(() => this.tick(), nextTickDelay(deadline, now))', $component);
        $this->assertStringNotContainsString('15000', $component);
        // À zéro : événement qui masque les boutons d'achat et la barre fixe.
        $this->assertStringContainsString("new CustomEvent('pack-registrations-closed'", $component);
        $this->assertStringContainsString("createServerClock({ url: '/heure-serveur' })", $js);

        $this->at('2026-10-12 15:00:00');
        $html = (string) $this->get(route('packs'))->assertOk()->getContent();
        // Boutons du haut et du bas, bouton de chacune des trois cartes et barre fixe : tous suivent la fin du compte à rebours.
        foreach (['packs-hero-button', 'pack-choose', 'packs-final-button', 'packs-sticky-bar'] as $element) {
            $this->assertStringContainsString('data-test="'.$element.'"', $html);
        }
        $this->assertSame(6, substr_count($html, 'x-on:pack-registrations-closed.window'));
    }
}
