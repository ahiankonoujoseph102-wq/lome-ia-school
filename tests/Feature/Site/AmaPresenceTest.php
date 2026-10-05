<?php

namespace Tests\Feature\Site;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Présence d'Ama dans le navigateur (resources/js/ama-presence.js et ama-call.js), exécutée avec Node.js :
 * bulle de présentation après 8 secondes cumulées sur le site, une seule fois par visite, en pause quand l'onglet est
 * masqué ; stockage bloqué sans erreur ; mouvement coupé si « moins d'animations » ou économie de données ; glissement
 * qui ferme la bulle ; bouche qui suit la synthèse vocale pendant toute la parole ; appareil sans écoute ni synthèse.
 */
class AmaPresenceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function simulate(string $scenario): array
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama-presence.js.');
        }

        $presence = json_encode('file://'.resource_path('js/ama-presence.js'));
        $call = json_encode('file://'.resource_path('js/ama-call.js'));
        $ama = json_encode('file://'.resource_path('js/ama.js'));
        $program = <<<JS
            const presence = await import({$presence});
            const { createVoiceCall } = await import({$call});
            const { speechSupport } = await import({$ama});
            const memory = () => { const data = {}; return { getItem: (k) => (k in data ? data[k] : null), setItem: (k, v) => { data[k] = String(v); }, data }; };
            const clock = () => {
                const env = { now: 0, timers: [] };
                env.timers.api = {
                    setTimeout(callback, delay) { const id = Symbol(); env.timers.push({ id, at: env.now + delay, callback }); return id; },
                    clearTimeout(id) { const i = env.timers.findIndex((t) => t.id === id); if (i >= 0) env.timers.splice(i, 1); },
                };
                env.advance = (ms) => {
                    const target = env.now + ms;
                    for (;;) {
                        env.timers.sort((a, b) => a.at - b.at);
                        const timer = env.timers[0];
                        if (!timer || timer.at > target) break;
                        env.timers.shift();
                        env.now = timer.at;
                        timer.callback();
                    }
                    env.now = target;
                };
                return env;
            };
            const out = {};
            {$scenario}
            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();

        return json_decode(trim($process->getOutput()), true);
    }

    public function test_the_bubble_comes_after_eight_seconds_on_the_site_once_per_visit(): void
    {
        $out = $this->simulate(<<<'JS'
            const storage = memory();
            const env = clock();
            let due = 0;
            // Première page : 5 secondes, puis le visiteur change de page (temps gardé).
            let timer = presence.createNudgeTimer({ storage, onDue: () => due++, now: () => env.now, timers: env.timers.api });
            timer.arm();
            env.advance(5000);
            timer.pause();
            out.afterFirstPage = { due, time: presence.timeOnSite(storage) };
            // Deuxième page : il ne reste que 3 secondes.
            timer = presence.createNudgeTimer({ storage, onDue: () => { due++; presence.markNudgeDone(storage); timer.stop(); }, now: () => env.now, timers: env.timers.api });
            timer.arm();
            env.advance(2999);
            out.before = due;
            env.advance(1);
            out.after = due;
            // Troisième page de la même visite : plus jamais.
            const third = presence.createNudgeTimer({ storage, onDue: () => due++, now: () => env.now, timers: env.timers.api });
            third.arm();
            env.advance(60000);
            out.third = { due, active: third.active };
            JS);

        $this->assertSame(['due' => 0, 'time' => 5000], $out['afterFirstPage']);
        $this->assertSame(0, $out['before']);
        $this->assertSame(1, $out['after']);
        $this->assertSame(['due' => 1, 'active' => false], $out['third']);
    }

    public function test_a_hidden_tab_pauses_the_count_and_a_blocked_storage_raises_no_error(): void
    {
        $out = $this->simulate(<<<'JS'
            const storage = memory();
            const env = clock();
            let due = 0;
            const timer = presence.createNudgeTimer({ storage, onDue: () => due++, now: () => env.now, timers: env.timers.api });
            timer.arm();
            env.advance(4000);
            timer.pause();           // onglet masqué
            env.advance(30000);
            out.hidden = due;
            timer.arm();             // de retour
            env.advance(3999);
            out.justBefore = due;
            env.advance(1);
            out.due = due;

            const blocked = { getItem() { throw new Error('bloqué'); }, setItem() { throw new Error('bloqué'); } };
            let blockedDue = 0;
            const other = presence.createNudgeTimer({ storage: blocked, onDue: () => blockedDue++, now: () => env.now, timers: env.timers.api });
            other.arm();
            env.advance(8000);
            presence.markNudgeDone(blocked);
            out.blocked = { due: blockedDue, done: presence.nudgeDone(blocked), remaining: presence.remainingMs(null) };
            JS);

        $this->assertSame(0, $out['hidden']);
        $this->assertSame(0, $out['justBefore']);
        $this->assertSame(1, $out['due']);
        $this->assertSame(['due' => 1, 'done' => false, 'remaining' => 8000], $out['blocked']);
    }

    public function test_motion_gesture_and_blink_helpers(): void
    {
        $out = $this->simulate(<<<'JS'
            const media = (reduce) => ({ matchMedia: (q) => ({ matches: reduce && q.includes('reduce') }), navigator: {} });
            out.motion = presence.motionAllowed(media(false));
            out.reduced = presence.motionAllowed(media(true));
            out.saveData = presence.motionAllowed({ ...media(false), navigator: { connection: { saveData: true } } });
            out.noMatchMedia = presence.motionAllowed({ navigator: {} });
            out.swipes = [presence.isSwipe(-60, 5), presence.isSwipe(45, -10), presence.isSwipe(20, 0), presence.isSwipe(50, 60), presence.isSwipe(0, -80)];
            out.blink = [presence.blinkDelay(0), presence.blinkDelay(1)];
            out.beats = [presence.nextBeat('a'), presence.nextBeat('b')];
            out.avoid = presence.NUDGE_AVOID;
            JS);

        $this->assertTrue($out['motion']);
        $this->assertFalse($out['reduced']);
        $this->assertFalse($out['saveData']);
        $this->assertTrue($out['noMatchMedia']);
        // Glissement sur le côté : fermé ; petit mouvement ou défilement vertical : rien.
        $this->assertSame([true, true, false, false, false], $out['swipes']);
        $this->assertSame([3000, 6000], $out['blink']);
        $this->assertSame(['b', 'a'], $out['beats']);
        // Jamais au-dessus d'un bouton, d'un champ, d'un bouton doré (paiement, inscription) ni de la barre d'achat.
        foreach (['button', 'input:not([type=hidden])', 'select', 'textarea', '.btn-gold', '[data-ama-avoid]', '[data-bottom-bar] a'] as $selector) {
            $this->assertStringContainsString($selector, $out['avoid']);
        }
    }

    public function test_the_mouth_moves_for_the_whole_speech_with_the_speech_synthesis_events(): void
    {
        $out = $this->simulate(<<<'JS'
            const env = clock();
            const events = [];
            const states = [];
            let current = null;
            const synth = {
                speak(utterance) { current = utterance; utterance.onstart?.(); },
                cancel() { current = null; },
                getVoices: () => [],
            };
            const Recognition = function () { this.start = () => {}; this.stop = () => {}; this.abort = () => {}; };
            const call = createVoiceCall({
                Recognition, synth, Utterance: function (text) { this.text = text; },
                ask: () => new Promise(() => {}),
                timers: env.timers.api, now: () => env.now,
                onState: (state) => states.push(state),
                onSpeech: (event) => events.push(event),
            });
            call.start();   // accueil lu à voix haute
            current.onboundary?.({ name: 'word' });
            current.onboundary?.({ name: 'word' });
            out.during = [...events];
            const first = current;
            // Fin de chaque phrase de l'accueil : la bouche se ferme, se rouvre pour la phrase suivante, puis Ama écoute.
            let guard = 0;
            while (current && guard++ < 10) { const sentence = current; current = null; sentence.onend(); }
            out.after = [...events];
            out.states = states;
            // Un événement tardif d'une phrase interrompue ne rouvre jamais la bouche.
            call.hangUp();
            const before = events.length;
            first.onstart?.();
            first.onboundary?.();
            out.late = events.length - before;
            JS);

        $this->assertSame(['start', 'boundary', 'boundary'], $out['during']);
        $this->assertSame(['start', 'boundary', 'boundary', 'end'], array_slice($out['after'], 0, 4));
        $this->assertSame('end', end($out['after']));
        $this->assertSame(count(array_keys($out['after'], 'start')), count(array_keys($out['after'], 'end')));
        // « Réfléchit » à l'écran jusqu'au vrai début de la voix (ici immédiat), puis « parle », puis « écoute ».
        $this->assertSame(['thinking', 'speaking', 'listening'], array_slice($out['states'], 0, 3));
        $this->assertSame(0, $out['late']);
    }

    public function test_a_device_without_listening_or_speech_synthesis_only_loses_the_voice(): void
    {
        $out = $this->simulate(<<<'JS'
            out.none = speechSupport({});
            out.listenOnly = speechSupport({ webkitSpeechRecognition: function () {} });
            out.speakOnly = speechSupport({ speechSynthesis: { speak() {} }, SpeechSynthesisUtterance: function () {} });
            // Sans matchMedia ni stockage : le visage et la bulle fonctionnent quand même.
            out.motion = presence.motionAllowed({});
            out.remaining = presence.remainingMs(undefined);
            JS);

        $this->assertSame(['listen' => false, 'speak' => false, 'Recognition' => null], $out['none']);
        $this->assertFalse($out['listenOnly']['speak']);
        $this->assertFalse($out['speakOnly']['listen']);
        $this->assertTrue($out['motion']);
        $this->assertSame(8000, $out['remaining']);
    }
}
