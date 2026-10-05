<?php

namespace Tests\Feature\Site;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Voix naturelle d'Ama : une seule source sonore par réponse, toujours (resources/js/ama-voice.js, exécuté avec Node.js).
 *
 * Le vrai lecteur (createElementPlayer) est branché sur un faux élément <audio> qui se comporte comme Chrome Android :
 * il peut mettre le son en pause de lui-même (micro, appel, notification : perte momentanée du haut-parleur) puis le
 * relancer de lui-même quand il retrouve le haut-parleur, même après que le site a abandonné ce son. C'est ce qui
 * faisait entendre deux voix l'une après l'autre (voix du téléphone, puis la fin de la phrase de Gemini).
 */
class AmaVoiceSingleSourceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function runNode(string $program): array
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama-voice.js.');
        }

        $program = str_replace(
            ['__VOICE__', '__CALL__'],
            [json_encode('file://'.resource_path('js/ama-voice.js')), json_encode('file://'.resource_path('js/ama-call.js'))],
            self::SETUP.$program,
        );

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();

        return json_decode(trim($process->getOutput()), true);
    }

    private const string SETUP = <<<'JS'
        const { createNaturalSynth, createElementPlayer, NaturalUtterance, PAUSE_GRACE_MS, NATURAL_TIMEOUT_MS } = await import(__VOICE__);
        const { createVoiceCall } = await import(__CALL__);
        const tick = async () => { for (let i = 0; i < 5; i += 1) await new Promise((resolve) => setImmediate(resolve)); };

        function setup({ phoneFallback = true, refusePlay = false, splitLength = 300 } = {}) {
            const env = { log: [], fetched: [], voiced: [], pending: [], now: 0, timers: [], fallbacks: [], silent: 0, phone: null, phoneTalking: false, overlap: false, events: [] };
            env.timersApi = {
                setTimeout(callback, delay) { const id = Symbol(); env.timers.push({ id, at: env.now + delay, callback }); return id; },
                clearTimeout(id) { env.timers = env.timers.filter((timer) => timer.id !== id); },
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
                    env.check();
                }
                env.now = target;
            };
            // Deux sources sonores en même temps : jamais.
            env.check = () => {
                if (el.audible && env.phoneTalking) env.overlap = true;
            };

            // Faux élément <audio> de Chrome Android.
            const listeners = {};
            const el = {
                _src: '', paused: true, ended: false, duration: 3, audible: false,
                get src() { return this._src; },
                set src(value) { this._src = value; this.ended = false; },
                addEventListener(name, callback) { (listeners[name] ??= []).push(callback); },
                emit(name) { (listeners[name] ?? []).forEach((callback) => callback()); },
                play() {
                    if (refusePlay) return Promise.reject(Object.assign(new Error('refusé'), { name: 'NotAllowedError' }));
                    if (!this._src) return Promise.reject(new Error('aucune source'));
                    this.paused = false;
                    this.audible = true;
                    this.emit('play');
                    if (!this.paused) this.emit('playing');
                    env.check();
                    return Promise.resolve();
                },
                pause() {
                    if (this.paused) return;
                    this.paused = true;
                    this.audible = false;
                    this.emit('pause');
                },
                removeAttribute(name) { if (name === 'src') this._src = ''; },
                load() { this.paused = true; this.audible = false; },
                // Le téléphone reprend le haut-parleur un moment (micro, notification…) : pause imposée.
                systemPause() { if (this.paused) return; this.paused = true; this.audible = false; this.emit('pause'); },
                // Le téléphone rend le haut-parleur : Chrome relance de lui-même le son qu'il avait mis en pause.
                systemResume() {
                    if (!this._src) { env.log.push('relance impossible : aucun son'); return; }
                    this.paused = false;
                    this.audible = true;
                    this.emit('play');
                    if (!this.paused) { this.emit('playing'); env.log.push('relance : ' + this._src.slice(5)); }
                    env.check();
                },
                finish() { this.ended = true; this.paused = true; this.audible = false; this.emit('pause'); this.emit('ended'); },
            };
            env.el = el;
            env.player = createElementPlayer(el, { setTimer: env.timersApi.setTimeout, clearTimer: env.timersApi.clearTimeout });
            env.fetchAudio = (text, { voiced }) => new Promise((resolve) => {
                env.fetched.push(text);
                env.voiced.push(voiced);
                env.pending.push({ text, resolve });
            });
            env.answer = async (text, kind = 'audio') => {
                const index = env.pending.findIndex((item) => item.text === text);
                const [item] = env.pending.splice(index, 1);
                item.resolve(kind === 'audio' ? { url: 'blob:' + text, release: () => {} } : null);
                await tick();
                env.check();
            };
            env.fallback = {
                speak(utterance) { env.phone = utterance; env.log.push('téléphone : ' + utterance.text); },
                cancel() { env.phone = null; env.phoneTalking = false; },
                getVoices: () => [],
            };
            env.startPhone = () => { env.phoneTalking = true; env.phone.onstart?.(); env.check(); };
            env.endPhone = () => { const utterance = env.phone; env.phone = null; env.phoneTalking = false; utterance.onend?.(); };
            env.synth = createNaturalSynth({
                fallback: env.fallback,
                FallbackUtterance: function (text) { this.text = text; },
                fetchAudio: env.fetchAudio,
                player: env.player,
                timers: env.timersApi,
                now: () => env.now,
                phoneFallback,
                splitLength,
                onFallback: (cause, waited, outcome) => env.fallbacks.push(cause + ' → ' + outcome),
                onSilent: () => { env.silent += 1; },
            });
            env.utterance = (text) => {
                const utterance = new NaturalUtterance(text);
                utterance.onstart = () => env.events.push('début');
                utterance.onend = () => env.events.push('fin');
                return utterance;
            };
            return env;
        }
        const out = {};

        JS;

    public function test_a_sound_paused_then_resumed_by_the_phone_never_plays_again_after_the_phone_voice(): void
    {
        $out = $this->runNode(<<<'JS'
            // Le défaut constaté sur le Pixel 6 : le téléphone met le son de Gemini en pause au moment où il démarre, la
            // voix du téléphone prend le relais, puis Chrome relance de lui-même le son de Gemini (pendant et après).
            {
                const env = setup();
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                // Le son est chargé et lancé, mais le téléphone le coupe aussitôt (avant la moindre syllabe).
                env.el.play = function () { this.paused = false; this.emit('play'); this.paused = true; this.emit('pause'); return Promise.resolve(); };
                await env.answer('Vous payez par Mobile Money.');
                env.advance(PAUSE_GRACE_MS - 1);
                out.beforeGrace = env.log.filter((line) => line.startsWith('téléphone')).length;
                env.advance(1);
                out.afterGrace = { log: [...env.log], reported: env.fallbacks, src: env.el.src };
                // La voix du téléphone parle ; Chrome essaie de relancer le son de Gemini pendant ET après elle.
                env.startPhone();
                env.el.systemResume();
                env.endPhone();
                env.el.systemResume();
                env.advance(10000);
                out.bug = { log: env.log, audible: env.el.audible, overlap: env.overlap, events: env.events };
            }
            console.log(JSON.stringify(out));
            JS);

        // Le téléphone n'est pris qu'après le court délai de grâce (le son aurait pu repartir tout seul).
        $this->assertSame(0, $out['beforeGrace']);
        $this->assertSame(['téléphone : Vous payez par Mobile Money.'], $out['afterGrace']['log']);
        $this->assertSame(['playback_paused → phone'], $out['afterGrace']['reported']);
        // Le son abandonné est vidé AVANT que la voix du téléphone commence : plus aucune source dans le lecteur.
        $this->assertSame('', $out['afterGrace']['src']);
        // Chrome ne peut plus rien relancer : ni pendant la voix du téléphone, ni après.
        $this->assertSame([
            'téléphone : Vous payez par Mobile Money.',
            'relance impossible : aucun son',
            'relance impossible : aucun son',
        ], $out['bug']['log']);
        $this->assertFalse($out['bug']['audible']);
        $this->assertFalse($out['bug']['overlap']);
        $this->assertSame(['début', 'fin'], $out['bug']['events']);
    }

    public function test_once_gemini_was_heard_a_cut_never_brings_the_phone_voice_nor_a_restart(): void
    {
        $out = $this->runNode(<<<'JS'
            // 1. Gemini parle, le téléphone coupe le son et ne le relance pas : le reste de la réponse est affiché, sans
            //    voix du téléphone (jamais deux voix l'une après l'autre pour une même réponse).
            {
                const env = setup();
                env.synth.speak(env.utterance('Elle coûte 25 000 FCFA.'));
                await env.answer('Elle coûte 25 000 FCFA.');
                out.heard = env.el.audible;
                env.el.systemPause();
                env.advance(PAUSE_GRACE_MS);
                out.cut = { log: [...env.log], reported: [...env.fallbacks], silent: env.silent, src: env.el.src, events: [...env.events] };
                env.el.systemResume();
                env.advance(5000);
                out.after = { log: env.log, audible: env.el.audible };
            }
            // 2. Coupure brève : Chrome relance le son de lui-même pendant le délai de grâce → Gemini continue, seule.
            {
                const env = setup();
                env.synth.speak(env.utterance('Elle coûte 25 000 FCFA.'));
                await env.answer('Elle coûte 25 000 FCFA.');
                env.el.systemPause();
                env.advance(600);
                env.el.systemResume();
                env.advance(PAUSE_GRACE_MS);
                env.el.finish();
                out.brief = { log: env.log, reported: env.fallbacks, silent: env.silent, events: env.events };
            }
            // 3. Toucher le cercle (interruption) : le son est vidé, une relance du téléphone ne rejoue rien.
            {
                const env = setup();
                env.synth.speak(env.utterance('Elle coûte 25 000 FCFA.'));
                await env.answer('Elle coûte 25 000 FCFA.');
                env.el.systemPause();
                env.synth.cancel();
                env.el.systemResume();
                out.cancel = { log: env.log, audible: env.el.audible };
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertTrue($out['heard']);
        $this->assertSame([], $out['cut']['log']);
        $this->assertSame(['playback_paused → silent'], $out['cut']['reported']);
        $this->assertSame(1, $out['cut']['silent']);
        $this->assertSame('', $out['cut']['src']);
        $this->assertSame(['début', 'fin'], $out['cut']['events']);
        $this->assertSame(['relance impossible : aucun son'], $out['after']['log']);
        $this->assertFalse($out['after']['audible']);

        $this->assertSame(['relance : Elle coûte 25 000 FCFA.'], $out['brief']['log']);
        $this->assertSame([], $out['brief']['reported']);
        $this->assertSame(0, $out['brief']['silent']);
        $this->assertSame(['début', 'fin'], $out['brief']['events']);

        $this->assertSame(['relance impossible : aucun son'], $out['cancel']['log']);
        $this->assertFalse($out['cancel']['audible']);
    }

    public function test_with_voice_interruption_a_cut_sentence_is_read_again_by_gemini_with_the_same_audio(): void
    {
        $out = $this->runNode(<<<'JS'
            {
                const env = setup();
                const mics = [];
                const Recognition = function () { this.start = () => {}; this.stop = () => this.onend?.(); this.abort = () => {}; mics.push(this); };
                const voiceCall = createVoiceCall({
                    Recognition,
                    synth: env.synth,
                    Utterance: NaturalUtterance,
                    ask: () => new Promise(() => {}),
                    timers: env.timersApi,
                    now: () => env.now,
                    bargeIn: true,
                    onBargeInUnavailable: () => env.log.push('interruption à la voix abandonnée'),
                });
                voiceCall.start();
                await env.answer(env.fetched[0]);
                out.micDuringSpeech = mics.length;
                // Le micro ouvert pendant la lecture coupe le son de Gemini sur ce téléphone, sans le relancer.
                env.el.systemPause();
                env.advance(PAUSE_GRACE_MS);
                await tick();
                out.after = { log: env.log, fetched: env.fetched.length, audible: env.el.audible, src: env.el.src, phone: env.phone };
                voiceCall.hangUp();
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(1, $out['micDuringSpeech']);
        // Comme avant pour la voix du téléphone : interruption à la voix abandonnée et phrase relue… par la même voix de
        // Gemini, avec le même audio : aucune nouvelle demande à Google, jamais la voix du téléphone.
        $this->assertSame(['interruption à la voix abandonnée'], $out['after']['log']);
        $this->assertSame(1, $out['after']['fetched']);
        $this->assertTrue($out['after']['audible']);
        $this->assertStringStartsWith('blob:', $out['after']['src']);
        $this->assertNull($out['after']['phone']);
    }

    public function test_the_rest_of_an_answer_started_by_gemini_is_never_read_by_the_phone_voice(): void
    {
        $out = $this->runNode(<<<'JS'
            // 1. Lecture à voix haute d'une longue réponse : la première phrase est dite par Gemini, la suite est demandée en
            //    signalant que Gemini a été entendu (« voiced ») ; le serveur la refuse (limite par minute) : elle reste
            //    affichée, jamais lue par la voix du téléphone.
            {
                const env = setup({ splitLength: 20 });
                env.synth.speak(env.utterance('Phrase un. Phrase deux. Phrase trois.'));
                out.before = [...env.fetched];
                await env.answer('Phrase un.');
                out.voiced = { fetched: [...env.fetched], voiced: [...env.voiced] };
                env.el.finish();
                await env.answer('Phrase deux. Phrase trois.', 'refus');
                env.advance(0);
                out.refused = { log: env.log, silent: env.silent, events: env.events, reported: env.fallbacks };
            }
            // 2. Mode appel : la suite est trop lente (délai dépassé) après une première phrase entendue : affichée sans voix.
            {
                const env = setup({ splitLength: 20 });
                env.synth.prepare(['Phrase un.', 'Phrase deux.', 'Phrase trois.']);
                env.synth.speak(env.utterance('Phrase un.'));
                await env.answer('Phrase un.');
                env.el.finish();
                await tick();
                env.synth.speak(env.utterance('Phrase deux.'));
                env.advance(NATURAL_TIMEOUT_MS);
                env.synth.speak(env.utterance('Phrase trois.'));
                env.advance(0);
                await env.answer('Phrase deux. Phrase trois.');
                env.advance(0);
                out.call = { log: env.log, reported: env.fallbacks, silent: env.silent, audible: env.el.audible };
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(['Phrase un.'], $out['before']);
        $this->assertSame(['fetched' => ['Phrase un.', 'Phrase deux. Phrase trois.'], 'voiced' => [false, true]], $out['voiced']);
        $this->assertSame([], $out['refused']['log']);
        $this->assertSame(1, $out['refused']['silent']);
        $this->assertSame(['début', 'fin'], $out['refused']['events']);
        // Le refus est compté par le serveur (avec « voiced ») : rien à signaler en plus par le navigateur.
        $this->assertSame([], $out['refused']['reported']);

        $this->assertSame([], $out['call']['log']);
        $this->assertSame(['timeout → silent'], $out['call']['reported']);
        $this->assertSame(2, $out['call']['silent']);
        $this->assertFalse($out['call']['audible']);
    }

    public function test_a_refused_playback_before_any_sound_gives_the_phone_voice_with_its_exact_cause(): void
    {
        $out = $this->runNode(<<<'JS'
            {
                const env = setup({ refusePlay: true });
                env.synth.speak(env.utterance('Bonjour, je suis Ama.'));
                await env.answer('Bonjour, je suis Ama.');
                await tick();
                out.refused = { log: env.log, reported: env.fallbacks, src: env.el.src };
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(['téléphone : Bonjour, je suis Ama.'], $out['refused']['log']);
        $this->assertSame(['playback_refused → phone'], $out['refused']['reported']);
        $this->assertSame('', $out['refused']['src']);
    }

    public function test_without_the_phone_voice_ama_only_uses_gemini_and_shows_the_answer_when_it_fails(): void
    {
        $out = $this->runNode(<<<'JS'
            // 1. Refus du serveur : rien n'est dit, la réponse reste affichée.
            {
                const env = setup({ phoneFallback: false });
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                await env.answer('Vous payez par Mobile Money.', 'refus');
                env.advance(0);
                out.refusal = { log: env.log, silent: env.silent, events: env.events };
            }
            // 2. Voix pas prête après le délai : rien n'est dit ; l'audio arrivé trop tard n'est jamais joué.
            {
                const env = setup({ phoneFallback: false });
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                env.advance(NATURAL_TIMEOUT_MS);
                await env.answer('Vous payez par Mobile Money.');
                env.advance(0);
                out.timeout = { log: env.log, audible: env.el.audible, reported: env.fallbacks, silent: env.silent, events: env.events };
            }
            // 3. Mode appel : la réponse est affichée sans voix et l'appel continue normalement (le micro se rouvre).
            {
                const env = setup({ phoneFallback: false });
                const states = [];
                const mics = [];
                const Recognition = function () { this.start = () => {}; this.stop = () => this.onend?.(); this.abort = () => {}; mics.push(this); };
                const voiceCall = createVoiceCall({
                    Recognition,
                    synth: env.synth,
                    Utterance: NaturalUtterance,
                    ask: () => Promise.resolve({ reply: 'Elle coûte 25 000 FCFA.', fallback: false }),
                    timers: env.timersApi,
                    now: () => env.now,
                    onState: (state) => states.push(state),
                });
                voiceCall.start();
                await env.answer(env.fetched[0], 'refus');
                env.advance(0);
                await tick();
                env.advance(0);
                out.call = { state: voiceCall.state, mics: mics.length, log: env.log, silent: env.silent };
                voiceCall.hangUp();
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(['log' => [], 'silent' => 1, 'events' => ['fin']], $out['refusal']);

        $this->assertSame([], $out['timeout']['log']);
        $this->assertFalse($out['timeout']['audible']);
        $this->assertSame(['timeout → silent'], $out['timeout']['reported']);
        $this->assertSame(1, $out['timeout']['silent']);
        $this->assertSame(['fin'], $out['timeout']['events']);

        $this->assertSame('listening', $out['call']['state']);
        $this->assertSame(1, $out['call']['mics']);
        $this->assertSame([], $out['call']['log']);
        // L'accueil (deux phrases) n'est pas dit : il reste affiché.
        $this->assertSame(2, $out['call']['silent']);
    }
}
