<?php

namespace Tests\Feature\Site;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Mode appel d'Ama : écoute plus attentive et interruption à la voix (resources/js/ama-call.js), exécutés avec
 * Node.js et de fausses fonctions vocales (reconnaissance, synthèse, minuteries, horloge) :
 * pause en parlant, texte coupé, demande de répéter, interruption normale, bruit isolé ignoré, écho d'Ama ignoré,
 * interrupteur éteint, micro indisponible pendant la lecture, mesure des durées.
 */
class AmaListeningTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function simulate(string $scenario): array
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama-call.js.');
        }

        $module = json_encode('file://'.resource_path('js/ama-call.js'));
        $program = <<<JS
            const engine = await import({$module});
            const { createVoiceCall, PAUSE_MS, CUT_PAUSE_MS, MISHEARD } = engine;
            const tick = () => new Promise((resolve) => setImmediate(resolve));

            function setup(askImpl, options = {}) {
                const env = { log: [], recognitions: [], spoken: [], current: null, now: 0, timers: [], states: [], asked: [], askOptions: [], misheard: [], visitor: [], timing: [], unavailable: 0, cancels: 0, failStart: () => false };
                env.Recognition = function () {
                    this.results = [];
                    this.start = () => {
                        if (env.failStart(this)) { throw new Error('micro occupé'); }
                        env.log.push('micro ouvert');
                    };
                    this.stop = () => { this.onend?.(); };
                    this.abort = () => { this.aborted = true; env.log.push('micro coupé'); };
                    env.recognitions.push(this);
                };
                env.mic = () => env.recognitions.at(-1);
                // Un morceau reconnu (final ou provisoire) sur le micro ouvert en dernier.
                env.hear = (text, { isFinal = true, confidence = 0.9, mic = env.mic() } = {}) => {
                    const result = Object.assign([{ transcript: text, confidence: isFinal ? confidence : 0 }], { isFinal });
                    if (mic.results.length && !mic.results.at(-1).isFinal) { mic.results.pop(); }
                    mic.results.push(result);
                    mic.onresult?.({ resultIndex: mic.results.length - 1, results: mic.results });
                };
                // Le navigateur ferme le micro (fin de session).
                env.close = (mic = env.mic()) => { mic.onend?.(); };
                env.synth = {
                    speak(utterance) { env.current = utterance; env.spoken.push(utterance.text); utterance.onstart?.(); },
                    cancel() { env.cancels += 1; env.current = null; },
                    getVoices: () => [{ lang: 'fr-FR', name: 'Google français' }],
                };
                env.finishSentence = () => { const utterance = env.current; env.current = null; utterance?.onend?.(); };
                env.finishAll = () => { while (env.current) env.finishSentence(); };
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
                env.call = createVoiceCall({
                    Recognition: env.Recognition,
                    synth: env.synth,
                    Utterance: function (text) { this.text = text; },
                    ask: (text, opts) => { env.asked.push(text); env.askOptions.push({ interrupted: opts.interrupted }); return askImpl(text, opts, env); },
                    timers: {
                        setTimeout(callback, delay) { const id = Symbol(); env.timers.push({ id, at: env.now + delay, callback }); return id; },
                        clearTimeout(id) { env.timers = env.timers.filter((timer) => timer.id !== id); },
                    },
                    now: () => env.now,
                    bargeIn: options.bargeIn ?? false,
                    onState: (state) => env.states.push(state),
                    onVisitor: (text) => env.visitor.push(text),
                    onMisheard: (text) => env.misheard.push(text),
                    onBargeInUnavailable: () => { env.unavailable += 1; },
                    onTiming: (durations, result) => env.timing.push({ durations, timing: result.timing }),
                });
                // Appel démarré, accueil lu : Ama écoute.
                env.ready = () => { env.call.start(); env.finishAll(); };
                return env;
            }

            // Ama répond par deux phrases (réponse lente : le visiteur a le temps de lui couper la parole).
            const longReply = (text, { onText }, env) => {
                env.now += 400;
                onText('La formation Agents IA coûte vingt-cinq mille francs. ');
                env.now += 300;
                onText('Elle se déroule en direct sur Google Meet chaque soir.');
                return Promise.resolve({ reply: 'La formation Agents IA coûte vingt-cinq mille francs. Elle se déroule en direct sur Google Meet chaque soir.', fallback: false, conversation: 'c', timing: 42 });
            };

            const out = {};
            {$scenario}
            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();

        return json_decode(trim($process->getOutput()), true);
    }

    public function test_a_pause_while_speaking_does_not_cut_the_sentence(): void
    {
        $out = $this->simulate(<<<'JS'
            const env = setup(longReply);
            env.ready();
            // Exemple réel : le visiteur hésite, fait une pause d'une seconde, puis pose sa question.
            env.hear('oui d\'accord bien d\'abord je voudrais d\'abord savoir');
            env.close();
            env.advance(1000);
            out.duringPause = { asked: env.asked.length, state: env.call.state, mics: env.recognitions.length };
            env.hear('c\'est quoi l\'intelligence artificielle', { isFinal: false });
            env.advance(1200);
            out.stillTalking = env.asked.length;
            env.hear('c\'est quoi l\'intelligence artificielle');
            env.advance(PAUSE_MS - 1);
            out.justBefore = env.asked.length;
            env.advance(1);
            await tick();
            out.asked = env.asked;
            out.misheard = env.misheard;
            JS);

        // Rien n'est envoyé pendant la pause : le micro est rouvert et les morceaux sont regroupés.
        $this->assertSame(['asked' => 0, 'state' => 'listening', 'mics' => 2], $out['duringPause']);
        $this->assertSame(0, $out['stillTalking']);
        $this->assertSame(0, $out['justBefore']);
        $this->assertSame(['oui d\'accord bien d\'abord je voudrais d\'abord savoir c\'est quoi l\'intelligence artificielle'], $out['asked']);
        $this->assertSame([], $out['misheard']);
    }

    public function test_a_cut_sentence_waits_a_little_then_ama_asks_to_repeat_without_any_request(): void
    {
        $out = $this->simulate(<<<'JS'
            // 1. Phrase coupée qui se termine pendant l'attente supplémentaire : envoyée en entier.
            {
                const env = setup(longReply);
                env.ready();
                env.hear('je voudrais savoir le prix de');
                env.advance(PAUSE_MS);
                out.waited = env.asked.length;
                env.hear('la formation agents IA');
                env.advance(PAUSE_MS);
                out.completed = env.asked;
            }
            // 2. Phrase restée coupée : Ama demande de répéter, rien n'est envoyé.
            {
                const env = setup(longReply);
                env.ready();
                env.hear('je voudrais d\'abord savoir');
                env.advance(PAUSE_MS + CUT_PAUSE_MS);
                out.cut = { asked: env.asked, misheard: env.misheard, said: env.current?.text, state: env.call.state };
                env.finishAll();
                out.afterRepeat = env.call.state;
            }
            JS);

        $this->assertSame(0, $out['waited']);
        $this->assertSame(['je voudrais savoir le prix de la formation agents IA'], $out['completed']);
        $this->assertSame([], $out['cut']['asked']);
        $this->assertSame(['Excusez-moi, je n\'ai pas bien entendu. Pouvez-vous répéter votre question ?'], $out['cut']['misheard']);
        $this->assertSame('Excusez-moi, je n\'ai pas bien entendu.', $out['cut']['said']);
        $this->assertSame('speaking', $out['cut']['state']);
        $this->assertSame('listening', $out['afterRepeat']);
    }

    public function test_an_empty_hesitant_or_unsure_sentence_gets_a_polite_repeat_request(): void
    {
        $out = $this->simulate(<<<'JS'
            const cases = { hesitation: ['euh hum', 0.9], lettre: ['a', 0.9], peuSur: ['combien la formation', 0.12] };
            for (const [name, [text, confidence]] of Object.entries(cases)) {
                const env = setup(longReply);
                env.ready();
                env.hear(text, { confidence });
                env.advance(PAUSE_MS);
                out[name] = { asked: env.asked.length, misheard: env.misheard.length, said: env.current?.text };
            }
            // Une réponse courte reste une vraie réponse.
            {
                const env = setup(longReply);
                env.ready();
                env.hear('oui');
                env.advance(PAUSE_MS);
                out.oui = env.asked;
            }
            // Le serveur peut aussi répondre « mal entendu » (sans appeler Gemini) : même phrase, rien n'est compté.
            {
                const env = setup(() => Promise.resolve({ reply: '', notice: MISHEARD, misheard: true }));
                env.ready();
                env.hear('hmm bon');
                env.advance(PAUSE_MS);
                env.hear('ah');
                env.advance(PAUSE_MS);
                out.localOnly = env.asked.length;
            }
            JS);

        foreach (['hesitation', 'lettre', 'peuSur'] as $case) {
            $this->assertSame(['asked' => 0, 'misheard' => 1, 'said' => 'Excusez-moi, je n\'ai pas bien entendu.'], $out[$case], $case);
        }
        $this->assertSame(['oui'], $out['oui']);
        $this->assertSame(0, $out['localOnly']);
    }

    public function test_the_visitor_interrupts_ama_by_voice_and_she_listens_until_the_end(): void
    {
        $out = $this->simulate(<<<'JS'
            const env = setup(longReply, { bargeIn: true });
            env.ready();
            env.hear('quel est le prix de la formation agents IA');
            env.advance(PAUSE_MS);
            await tick();
            out.speaking = { state: env.call.state, said: env.current?.text, micDuringSpeech: env.recognitions.length };
            const spokenBefore = env.spoken.length;
            const cancelsBefore = env.cancels;
            // Le visiteur parle pendant qu'Ama parle : elle se tait tout de suite, sans rien dire.
            env.hear('attendez je veux', { isFinal: false });
            out.interrupted = { state: env.call.state, cancelled: env.cancels > cancelsBefore, current: env.current, saidAfter: env.spoken.slice(spokenBefore), asked: env.asked.length };
            // Elle écoute jusqu'à la fin de sa phrase (même délai de silence), puis répond à ce qu'il a dit.
            env.hear('attendez je veux payer avec Flooz');
            env.advance(PAUSE_MS - 1);
            out.beforeEnd = env.asked.length;
            env.advance(1);
            await tick();
            out.asked = env.asked;
            out.options = env.askOptions;
            out.spokenWords = env.spoken.join(' ').toLowerCase();
            env.call.hangUp();
            JS);

        $this->assertSame('speaking', $out['speaking']['state']);
        $this->assertSame('La formation Agents IA coûte vingt-cinq mille francs.', $out['speaking']['said']);
        $this->assertSame(['state' => 'listening', 'cancelled' => true, 'current' => null, 'saidAfter' => [], 'asked' => 1], $out['interrupted']);
        $this->assertSame(1, $out['beforeEnd']);
        $this->assertSame(['quel est le prix de la formation agents IA', 'attendez je veux payer avec Flooz'], $out['asked']);
        // La phrase qui a coupé la parole est signalée au serveur (Ama répond directement, sans « patientez »).
        $this->assertSame([['interrupted' => false], ['interrupted' => true]], $out['options']);
        $this->assertStringNotContainsString('patientez', $out['spokenWords']);
        $this->assertStringNotContainsString('je termine', $out['spokenWords']);
    }

    public function test_an_isolated_noise_or_a_single_word_never_interrupts(): void
    {
        $out = $this->simulate(<<<'JS'
            const env = setup(longReply, { bargeIn: true });
            env.ready();
            env.hear('quel est le prix');
            env.advance(PAUSE_MS);
            await tick();
            const cancels = env.cancels;
            for (const noise of ['euh', 'ok', 'hum hum', 'bah']) {
                env.hear(noise, { isFinal: false });
            }
            out.noise = { state: env.call.state, cancelled: env.cancels !== cancels, said: env.current?.text, asked: env.asked.length };
            env.call.hangUp();
            JS);

        $this->assertSame(['state' => 'speaking', 'cancelled' => false, 'said' => 'La formation Agents IA coûte vingt-cinq mille francs.', 'asked' => 1], $out['noise']);
    }

    public function test_ama_own_voice_heard_by_the_microphone_is_ignored(): void
    {
        $out = $this->simulate(<<<'JS'
            const env = setup(longReply, { bargeIn: true });
            env.ready();
            env.hear('quel est le prix');
            env.advance(PAUSE_MS);
            await tick();
            const cancels = env.cancels;
            // Le micro capte la voix d'Ama (écho), en morceaux puis en entier.
            env.hear('la formation agents IA coûte', { isFinal: false });
            env.hear('la formation agents IA coûte vingt-cinq mille francs');
            out.echo = { state: env.call.state, cancelled: env.cancels !== cancels, asked: env.asked.length };
            // Ama continue : la phrase suivante est lue, puis elle écoute sans rien envoyer de l'écho.
            env.finishSentence();
            out.next = env.current?.text;
            env.finishAll();
            env.advance(PAUSE_MS * 2);
            out.after = { state: env.call.state, asked: env.asked.length };
            // Écho mêlé à une vraie parole du visiteur : la parole du visiteur interrompt.
            env.hear('quel est le prix');
            env.advance(PAUSE_MS);
            await tick();
            env.hear('elle se déroule en direct non attendez combien de séances', { isFinal: false });
            out.mixed = env.call.state;
            env.call.hangUp();
            JS);

        $this->assertSame(['state' => 'speaking', 'cancelled' => false, 'asked' => 1], $out['echo']);
        $this->assertSame('Elle se déroule en direct sur Google Meet chaque soir.', $out['next']);
        $this->assertSame(['state' => 'listening', 'asked' => 1], $out['after']);
        $this->assertSame('listening', $out['mixed']);
    }

    public function test_with_the_switch_off_the_microphone_stays_closed_while_ama_speaks(): void
    {
        $out = $this->simulate(<<<'JS'
            const env = setup(longReply, { bargeIn: false });
            env.call.start();
            out.greeting = { state: env.call.state, mics: env.recognitions.length };
            env.finishAll();
            env.hear('quel est le prix');
            env.advance(PAUSE_MS);
            await tick();
            const mics = env.recognitions.length;
            out.speaking = { state: env.call.state, micsOpened: env.recognitions.length - mics, open: env.recognitions.filter((mic) => !mic.aborted).length };
            // Toucher le cercle coupe toujours la parole.
            env.call.touch();
            out.touch = { state: env.call.state, current: env.current };
            env.call.hangUp();
            JS);

        $this->assertSame(['state' => 'speaking', 'mics' => 0], $out['greeting']);
        $this->assertSame(['state' => 'speaking', 'micsOpened' => 0, 'open' => 0], $out['speaking']);
        $this->assertSame(['state' => 'listening', 'current' => null], $out['touch']);
    }

    public function test_a_microphone_unavailable_during_speech_never_breaks_the_call(): void
    {
        $out = $this->simulate(<<<'JS'
            // 1. Le téléphone refuse d'ouvrir le micro pendant la lecture : Ama continue, on n'essaie plus.
            {
                const env = setup(longReply, { bargeIn: true });
                env.failStart = () => env.current !== null;
                env.ready();
                out.greetingDone = env.call.state;
                env.hear('quel est le prix');
                env.advance(PAUSE_MS);
                await tick();
                out.refused = { state: env.call.state, said: env.current?.text, unavailable: env.unavailable };
                env.finishAll();
                out.afterSpeech = { state: env.call.state, unavailable: env.unavailable };
            }
            // 2. Le micro coupe la voix d'Ama (erreur « interrupted ») : la phrase est relue, sans micro.
            {
                const env = setup(longReply, { bargeIn: true });
                env.ready();
                env.hear('quel est le prix');
                env.advance(PAUSE_MS);
                await tick();
                const before = env.spoken.length;
                const utterance = env.current;
                env.current = null;
                utterance.onerror({ error: 'interrupted' });
                out.cutVoice = { again: env.spoken.slice(before), unavailable: env.unavailable, state: env.call.state, barge: env.recognitions.filter((mic) => !mic.aborted).length };
                env.call.hangUp();
            }
            // 3. Erreur du micro pendant la lecture (« audio-capture ») : l'appel continue normalement.
            {
                const env = setup(longReply, { bargeIn: true });
                env.ready();
                env.hear('quel est le prix');
                env.advance(PAUSE_MS);
                await tick();
                env.mic().onerror({ error: 'audio-capture' });
                out.micError = { state: env.call.state, unavailable: env.unavailable, said: env.current?.text };
                env.call.hangUp();
            }
            JS);

        $this->assertSame('listening', $out['greetingDone']);
        $this->assertSame(['state' => 'speaking', 'said' => 'La formation Agents IA coûte vingt-cinq mille francs.', 'unavailable' => 1], $out['refused']);
        $this->assertSame(['state' => 'listening', 'unavailable' => 1], $out['afterSpeech']);
        $this->assertSame(['again' => ['La formation Agents IA coûte vingt-cinq mille francs.'], 'unavailable' => 1, 'state' => 'speaking', 'barge' => 0], $out['cutVoice']);
        $this->assertSame(['state' => 'speaking', 'unavailable' => 1, 'said' => 'La formation Agents IA coûte vingt-cinq mille francs.'], $out['micError']);
    }

    public function test_touching_the_circle_while_listening_sends_at_once_and_durations_are_measured(): void
    {
        $out = $this->simulate(<<<'JS'
            const env = setup(longReply);
            env.ready();
            env.hear('quel est le prix de la formation');
            env.advance(500);
            env.call.touch();
            await tick();
            out.asked = env.asked;
            out.timing = env.timing;
            env.call.hangUp();
            JS);

        $this->assertSame(['quel est le prix de la formation'], $out['asked']);
        // Durées d'un tour (ms) : attente du silence, premier morceau reçu, début de la voix ; avec l'identifiant de mesure.
        $this->assertSame([['durations' => ['pause' => 500, 'first' => 400, 'voice' => 400], 'timing' => 42]], $out['timing']);
    }

    public function test_pure_helpers(): void
    {
        $out = $this->simulate(<<<'JS'
            out.join = [
                engine.joinTranscripts(['bonjour', 'bonjour je voudrais', 'je voudrais']),
                engine.joinTranscripts(['oui d\'accord', 'c\'est quoi un prompt']),
            ];
            out.assess = ['', 'euh', 'oui', 'je voudrais savoir', 'je voudrais savoir ?', 'c\'est quoi l\'intelligence artificielle', 'le prix de'].map((text) => engine.assessPhrase(text));
            out.visitor = [
                engine.isVisitorSpeech('la formation coûte', 'La formation coûte vingt-cinq mille francs.'),
                engine.isVisitorSpeech('ok', 'Bonjour.'),
                engine.isVisitorSpeech('attendez une question', 'La formation coûte vingt-cinq mille francs.'),
            ];
            JS);

        $this->assertSame(['bonjour je voudrais', 'oui d\'accord c\'est quoi un prompt'], $out['join']);
        $this->assertSame(['misheard', 'misheard', 'send', 'cut', 'send', 'send', 'cut'], $out['assess']);
        $this->assertSame([false, false, true], $out['visitor']);
    }
}
