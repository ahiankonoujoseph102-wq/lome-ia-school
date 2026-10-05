<?php

namespace Tests\Feature\Site;

use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Voix naturelle d'Ama dans le navigateur (resources/js/ama-voice.js), exécutée avec Node.js, avec un faux serveur, un
 * faux lecteur audio, une fausse voix du téléphone et de fausses minuteries : lecture de l'audio, refus du serveur,
 * délai dépassé (réglable, 10 s par défaut), serveur injoignable, lecture impossible, audio arrivé trop tard jamais joué, une seule voix par réponse, prénom jamais envoyé, et mode appel complet
 * (ama-call.js) : première phrase seule puis la suite d'un bloc, bouche qui bouge, interruption et raccrocher.
 */
class AmaNaturalVoiceBrowserTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Faux serveur (réponses tenues en attente puis libérées), faux lecteur, fausse voix du téléphone, fausses minuteries.
     */
    private const string SETUP = <<<'JS'
        const { createNaturalSynth, NaturalUtterance, hidePrivateWords, splitForSpeech, NATURAL_TIMEOUT_MS, SPLIT_LENGTH, STREAM_WAIT_MS } = await import(__VOICE__);
        const { createVoiceCall } = await import(__CALL__);
        const tick = async () => { for (let i = 0; i < 5; i += 1) await new Promise((resolve) => setImmediate(resolve)); };

        function setup({ privateWords = [], splitLength = SPLIT_LENGTH, timeoutMs = NATURAL_TIMEOUT_MS } = {}) {
            const env = { log: [], fetched: [], pending: [], aborted: [], playing: null, phone: null, phoneSaid: [], fallbacks: [], waits: [], now: 0, timers: [], events: [] };
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
                }
                env.now = target;
            };
            env.fetchAudio = (text, { signal }) => new Promise((resolve) => {
                env.fetched.push(text);
                signal?.addEventListener('abort', () => env.aborted.push(text));
                env.pending.push({ text, resolve });
            });
            // Le serveur répond : audio (« audio ») ou voix du téléphone (« refus »).
            env.answer = async (text, kind = 'audio') => {
                const index = env.pending.findIndex((item) => item.text === text);
                const [item] = env.pending.splice(index, 1);
                item.resolve(kind === 'audio' ? { url: 'blob:' + text, release: () => env.log.push('libéré : ' + text) } : null);
                await tick();
            };
            // Le serveur du site est injoignable (réseau coupé, erreur 500, session expirée).
            env.unreachable = async (text) => {
                const index = env.pending.findIndex((item) => item.text === text);
                const [item] = env.pending.splice(index, 1);
                item.resolve(Promise.reject(new Error('réseau')));
                await tick();
            };
            env.player = {
                play(url, handlers) { env.playing = { url, handlers }; env.log.push('audio : ' + url.slice(5)); },
                stop() { env.playing = null; env.log.push('audio arrêté'); },
            };
            env.startAudio = () => env.playing.handlers.onstart();
            env.endAudio = () => { const playing = env.playing; env.playing = null; playing.handlers.onend(); };
            env.failAudio = () => { const playing = env.playing; env.playing = null; playing.handlers.onerror(); };
            env.fallback = {
                speak(utterance) { env.phone = utterance; env.phoneSaid.push(utterance.text); env.log.push('téléphone : ' + utterance.text); },
                cancel() { env.phone = null; env.log.push('téléphone arrêté'); },
                getVoices: () => [{ lang: 'fr-FR', name: 'Microsoft Denise Online (Natural)' }],
            };
            env.startPhone = () => env.phone.onstart?.();
            env.endPhone = () => { const utterance = env.phone; env.phone = null; utterance.onend?.(); };
            env.synth = createNaturalSynth({
                fallback: env.fallback,
                FallbackUtterance: function (text) { this.text = text; },
                fetchAudio: env.fetchAudio,
                player: env.player,
                timers: env.timersApi,
                now: () => env.now,
                privateWords,
                splitLength,
                timeoutMs,
                onFallback: (reason, waited) => { env.fallbacks.push(reason); env.waits.push(waited); },
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

    public function test_a_short_reply_makes_one_request_and_a_long_one_two(): void
    {
        $out = $this->runNode(<<<'JS'
            // 1. Réponse courte (sous la longueur réglée) : UNE seule demande pour tout le texte.
            {
                const env = setup();
                env.synth.speak(env.utterance('Phrase un. Phrase deux. Phrase trois.'));
                out.shortFetched = [...env.fetched];
                await env.answer('Phrase un. Phrase deux. Phrase trois.');
                out.shortPlaying = env.playing?.url;
                env.startAudio();
                env.endAudio();
                out.shortEvents = env.events;
                out.defaultLength = SPLIT_LENGTH;
            }
            // 2. Réponse plus longue que la longueur réglée : la première phrase seule, puis tout le reste d'un bloc.
            {
                const env = setup({ splitLength: 20 });
                env.synth.speak(env.utterance('Phrase un. Phrase deux. Phrase trois.'));
                out.fetched = [...env.fetched];
                await env.answer('Phrase un.');
                out.firstPlaying = env.playing?.url;
                env.startAudio();
                await env.answer('Phrase deux. Phrase trois.');
                env.endAudio();
                await tick();
                out.secondPlaying = env.playing?.url;
                env.startAudio();
                env.endAudio();
                out.events = env.events;
                out.phone = env.phoneSaid;
                out.split = splitForSpeech('Une seule phrase.');
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(300, $out['defaultLength']);
        $this->assertSame(['Phrase un. Phrase deux. Phrase trois.'], $out['shortFetched']);
        $this->assertSame('blob:Phrase un. Phrase deux. Phrase trois.', $out['shortPlaying']);
        $this->assertSame(['début', 'fin'], $out['shortEvents']);

        $this->assertSame(['Phrase un.', 'Phrase deux. Phrase trois.'], $out['fetched']);
        $this->assertSame('blob:Phrase un.', $out['firstPlaying']);
        $this->assertSame('blob:Phrase deux. Phrase trois.', $out['secondPlaying']);
        // La bouche d'Ama : un seul début, une seule fin.
        $this->assertSame(['début', 'fin'], $out['events']);
        $this->assertSame([], $out['phone']);
        $this->assertSame(['Une seule phrase.'], $out['split']);
    }

    public function test_a_refusal_a_delay_or_a_playback_error_gives_the_phone_voice_at_once(): void
    {
        $out = $this->runNode(<<<'JS'
            // 1. Le serveur refuse (quota, limite, erreur) : voix du téléphone aussitôt.
            {
                const env = setup();
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                await env.answer('Vous payez par Mobile Money.', 'refus');
                out.refusal = { phone: env.phoneSaid, voice: env.phone?.voice?.name ?? null };
                env.startPhone();
                env.endPhone();
                out.refusalEvents = env.events;
                out.refusalReported = env.fallbacks;
            }
            // 2. Audio pas prêt après 4 secondes : voix du téléphone, demande abandonnée, passage signalé.
            {
                const env = setup();
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                env.advance(NATURAL_TIMEOUT_MS - 1);
                out.beforeTimeout = env.phoneSaid.length;
                env.advance(1);
                out.timeout = { phone: env.phoneSaid, aborted: env.aborted, reported: env.fallbacks };
                // L'audio qui arrive trop tard n'est jamais joué.
                await env.answer('Vous payez par Mobile Money.');
                out.lateAudio = env.playing;
                env.startPhone();
                env.endPhone();
                out.timeoutEvents = env.events;
            }
            // 3. Lecture refusée par le navigateur : voix du téléphone.
            {
                const env = setup();
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                await env.answer('Vous payez par Mobile Money.');
                env.failAudio();
                out.playback = { phone: env.phoneSaid, reported: env.fallbacks };
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(['phone' => ['Vous payez par Mobile Money.'], 'voice' => null], $out['refusal']);
        $this->assertSame(['début', 'fin'], $out['refusalEvents']);
        // Le serveur a déjà compté son propre refus : rien à signaler en plus.
        $this->assertSame([], $out['refusalReported']);

        $this->assertSame(0, $out['beforeTimeout']);
        $this->assertSame(['phone' => ['Vous payez par Mobile Money.'], 'aborted' => ['Vous payez par Mobile Money.'], 'reported' => ['timeout']], $out['timeout']);
        $this->assertNull($out['lateAudio']);
        $this->assertSame(['début', 'fin'], $out['timeoutEvents']);

        $this->assertSame(['phone' => ['Vous payez par Mobile Money.'], 'reported' => ['playback']], $out['playback']);
    }

    public function test_the_first_name_is_never_sent_but_the_phone_voice_says_it(): void
    {
        $out = $this->runNode(<<<'JS'
            {
                const env = setup({ privateWords: ['Kossi'] });
                env.synth.speak(env.utterance('Bonsoir Kossi, je suis Ama.'));
                out.fetched = [...env.fetched];
                await env.answer('Bonsoir, je suis Ama.', 'refus');
                out.phone = env.phoneSaid;
                out.hidden = hidePrivateWords('Merci kossi !', ['Kossi']);
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(['Bonsoir, je suis Ama.'], $out['fetched']);
        $this->assertSame(['Bonsoir Kossi, je suis Ama.'], $out['phone']);
        $this->assertSame('Merci !', $out['hidden']);
    }

    public function test_the_call_mode_makes_one_request_for_a_short_answer_and_keeps_interruption_and_hang_up(): void
    {
        $out = $this->runNode(<<<'JS'
            function call(env, askImpl) {
                env.recognitions = [];
                env.mouth = [];
                env.Recognition = function () {
                    this.start = () => env.log.push('micro ouvert');
                    this.stop = () => this.onend?.();
                    this.abort = () => { this.aborted = true; env.log.push('micro coupé'); };
                    env.recognitions.push(this);
                };
                return createVoiceCall({
                    Recognition: env.Recognition,
                    synth: env.synth,
                    Utterance: NaturalUtterance,
                    ask: askImpl,
                    timers: env.timersApi,
                    now: () => env.now,
                    onSpeech: (event) => env.mouth.push(event),
                });
            }
            const say = (env, text) => {
                const mic = env.recognitions.at(-1);
                mic.onspeechstart?.();
                mic.onresult?.({ resultIndex: 0, results: [Object.assign([{ transcript: text }], { isFinal: true })] });
                mic.onend?.();
                env.advance(2000);
            };
            const reply = 'Elle coûte 25 000 FCFA. Vous payez par Mobile Money. Voulez-vous le lien ?';
            const asker = (holder) => (text, options) => new Promise((resolve) => { holder.onText = options.onText; holder.finish = () => resolve({ reply, fallback: false }); });

            // 1. Réponse courte : l'accueil, puis une réponse de trois phrases, chacun en UNE seule demande.
            {
                const env = setup();
                const holder = {};
                const voiceCall = call(env, asker(holder));
                voiceCall.start();
                out.greetingFetched = [...env.fetched];
                await env.answer(env.fetched[0]);
                env.startAudio();
                env.endAudio();
                env.advance(0);
                await tick();
                out.afterGreeting = voiceCall.state;
                env.fetched = [];
                say(env, 'Combien coûte la formation ?');
                holder.onText(reply + ' ');
                // La réponse arrive encore : on attend sa fin pour savoir si elle est courte.
                out.beforeEnd = env.fetched.length;
                holder.finish();
                await tick();
                out.replyFetched = [...env.fetched];
                await env.answer(reply);
                env.startAudio();
                out.speaking = voiceCall.state;
                env.endAudio();
                for (let i = 0; i < 3; i += 1) {
                    env.advance(0);
                    await tick();
                }
                out.afterReply = { state: voiceCall.state, fetched: env.fetched.length, phone: env.phoneSaid };
                out.mouth = env.mouth;
                voiceCall.hangUp();
            }

            // 2. Réponse longue (au-delà de la longueur réglée) : la première phrase seule, puis la suite d'un bloc.
            {
                const env = setup({ splitLength: 40 });
                const holder = {};
                const voiceCall = call(env, asker(holder));
                voiceCall.start();
                out.longGreeting = [...env.fetched];
                await env.answer(env.fetched[0]);
                env.startAudio();
                out.longGreetingRest = env.fetched.slice(1);
                await env.answer(env.fetched[1]);
                env.endAudio();
                await tick();
                env.startAudio();
                env.endAudio();
                await tick();
                env.fetched = [];
                say(env, 'Combien coûte la formation ?');
                holder.onText(reply + ' ');
                out.longFirst = [...env.fetched];
                holder.finish();
                await tick();
                await env.answer('Elle coûte 25 000 FCFA.');
                env.startAudio();
                out.longFetched = [...env.fetched];
                voiceCall.hangUp();
            }

            // 3. La fin de la réponse tarde : au bout de STREAM_WAIT_MS, ce qui est arrivé est demandé.
            {
                const env = setup();
                const holder = {};
                const voiceCall = call(env, asker(holder));
                voiceCall.start();
                await env.answer(env.fetched[0]);
                env.startAudio();
                env.endAudio();
                env.advance(0);
                await tick();
                env.fetched = [];
                say(env, 'Combien coûte la formation ?');
                holder.onText('Elle coûte 25 000 FCFA. ');
                env.advance(STREAM_WAIT_MS - 1);
                out.waiting = env.fetched.length;
                env.advance(1);
                out.afterWait = [...env.fetched];
                voiceCall.hangUp();
            }

            // 4. Interruption (toucher le cercle) pendant la voix naturelle, puis raccrocher.
            {
                const env = setup();
                const voiceCall = call(env, () => new Promise(() => {}));
                voiceCall.start();
                await env.answer(env.fetched[0]);
                env.startAudio();
                voiceCall.touch();
                out.interrupt = { state: voiceCall.state, playing: env.playing, stopped: env.log.includes('audio arrêté'), aborted: env.aborted.length > 0 };
                voiceCall.hangUp();
                out.hangUp = { state: voiceCall.state, playing: env.playing };
            }
            console.log(JSON.stringify(out));
            JS);

        // 1. Accueil et réponse courte : une seule demande chacun.
        $this->assertSame(['Bonjour, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.'], $out['greetingFetched']);
        $this->assertSame('listening', $out['afterGreeting']);
        $this->assertSame(0, $out['beforeEnd']);
        $this->assertSame(['Elle coûte 25 000 FCFA. Vous payez par Mobile Money. Voulez-vous le lien ?'], $out['replyFetched']);
        $this->assertSame('speaking', $out['speaking']);
        $this->assertSame(['state' => 'listening', 'fetched' => 1, 'phone' => []], $out['afterReply']);
        // La bouche bouge pendant l'audio.
        $this->assertContains('start', $out['mouth']);
        $this->assertContains('end', $out['mouth']);

        // 2. Réponse longue : deux demandes (première phrase, puis la suite dès qu'elle commence).
        $this->assertSame(['Bonjour, je suis Ama, l\'assistante de Lomé IA School.'], $out['longGreeting']);
        $this->assertSame(['Je vous écoute.'], $out['longGreetingRest']);
        $this->assertSame(['Elle coûte 25 000 FCFA.'], $out['longFirst']);
        $this->assertSame(['Elle coûte 25 000 FCFA.', 'Vous payez par Mobile Money. Voulez-vous le lien ?'], $out['longFetched']);

        // 3. Fin de réponse trop lente : pas d'attente au-delà de 1,5 s.
        $this->assertSame(0, $out['waiting']);
        $this->assertSame(['Elle coûte 25 000 FCFA.'], $out['afterWait']);

        // 4. Toucher le cercle coupe la voix naturelle et ouvre le micro ; raccrocher arrête tout.
        $this->assertSame(['state' => 'listening', 'playing' => null, 'stopped' => true, 'aborted' => true], $out['interrupt']);
        $this->assertSame(['state' => 'ended', 'playing' => null], $out['hangUp']);
    }

    public function test_after_a_fallback_the_rest_of_the_answer_keeps_the_phone_voice(): void
    {
        $out = $this->runNode(<<<'JS'
            {
                const env = setup();
                env.synth.prepare(['Phrase un.', 'Phrase deux.']);
                env.synth.speak(env.utterance('Phrase un.'));
                await env.answer('Phrase un. Phrase deux.', 'refus');
                env.startPhone();
                env.endPhone();
                env.synth.speak(env.utterance('Phrase deux.'));
                out.sameAnswer = { phone: [...env.phoneSaid], fetched: [...env.fetched] };
                env.endPhone();
                // Réponse suivante, plus tard : la voix naturelle est réessayée.
                env.advance(5000);
                env.synth.speak(env.utterance('Nouvelle réponse.'));
                out.nextAnswer = env.fetched;
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(['phone' => ['Phrase un.', 'Phrase deux.'], 'fetched' => ['Phrase un. Phrase deux.']], $out['sameAnswer']);
        $this->assertSame(['Phrase un. Phrase deux.', 'Nouvelle réponse.'], $out['nextAnswer']);
    }

    public function test_the_page_sends_the_natural_voice_settings_only_when_it_is_on(): void
    {
        config(['services.gemini.key' => 'cle-de-test-page-voix']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);

        $this->get(route('home'))->assertOk()->assertDontSee('fallbackEndpoint', false)->assertDontSee('cle-de-test-page-voix');

        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', 'natural_voice' => ['enabled' => true]]]);

        $this->get(route('home'))->assertOk()->assertSee('fallbackEndpoint', false)->assertDontSee('cle-de-test-page-voix');
    }

    public function test_the_delay_is_set_by_the_admin_and_late_audio_never_plays_over_the_phone_voice(): void
    {
        $out = $this->runNode(<<<'JS'
            // 1. Délai réglé à 10 s : à 4 s la voix naturelle est encore attendue ; à 10 s, voix du téléphone, attente signalée.
            {
                const env = setup({ timeoutMs: 10000 });
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                env.advance(4000);
                out.at4 = env.phoneSaid.length;
                env.advance(6000);
                out.at10 = { phone: env.phoneSaid, reported: env.fallbacks, waited: env.waits };
                // L'audio arrive 50 ms trop tard, pendant que le téléphone parle : jamais joué, aussitôt libéré.
                env.startPhone();
                env.advance(50);
                await env.answer('Vous payez par Mobile Money.');
                out.lateWhilePhone = { playing: env.playing, log: env.log.filter((line) => line.startsWith('audio') || line.startsWith('libéré')) };
                env.endPhone();
                out.events = env.events;
            }
            // 2. Réponse lue en deux demandes : la première est trop lente, la suite arrive à temps. La suite garde la voix
            //    du téléphone (une seule voix par réponse) : l'audio de la suite n'est jamais joué.
            {
                const env = setup({ splitLength: 20, timeoutMs: 5000 });
                env.synth.speak(env.utterance('Phrase un. Phrase deux. Phrase trois.'));
                await env.answer('Phrase deux. Phrase trois.');
                env.advance(5000);
                env.startPhone();
                env.endPhone();
                await env.answer('Phrase un.');
                env.advance(0);
                out.split = { phone: env.phoneSaid, playing: env.playing, audio: env.log.filter((line) => line.startsWith('audio : ')) };
            }
            // 3. Serveur injoignable : voix du téléphone tout de suite, cause « network » signalée.
            {
                const env = setup();
                env.synth.speak(env.utterance('Vous payez par Mobile Money.'));
                env.advance(300);
                await env.unreachable('Vous payez par Mobile Money.');
                out.network = { phone: env.phoneSaid, reported: env.fallbacks, waited: env.waits };
            }
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(0, $out['at4']);
        $this->assertSame(['phone' => ['Vous payez par Mobile Money.'], 'reported' => ['timeout'], 'waited' => [10000]], $out['at10']);
        $this->assertNull($out['lateWhilePhone']['playing']);
        $this->assertSame(['libéré : Vous payez par Mobile Money.'], $out['lateWhilePhone']['log']);
        $this->assertSame(['début', 'fin'], $out['events']);

        $this->assertSame(['Phrase un.', 'Phrase deux. Phrase trois.'], $out['split']['phone']);
        $this->assertNull($out['split']['playing']);
        $this->assertSame([], $out['split']['audio']);

        $this->assertSame(['phone' => ['Vous payez par Mobile Money.'], 'reported' => ['network'], 'waited' => [300]], $out['network']);
    }

    public function test_ama_shows_thinking_until_the_natural_voice_really_starts(): void
    {
        $out = $this->runNode(<<<'JS'
            const env = setup();
            const states = [];
            env.recognitions = [];
            const Recognition = function () {
                this.start = () => {};
                this.stop = () => this.onend?.();
                this.abort = () => {};
                env.recognitions.push(this);
            };
            let finish = null;
            let onText = null;
            const voiceCall = createVoiceCall({
                Recognition,
                synth: env.synth,
                Utterance: NaturalUtterance,
                ask: (text, options) => new Promise((resolve) => { onText = options.onText; finish = () => resolve({ reply: 'Elle coûte 25 000 FCFA.', fallback: false }); }),
                timers: env.timersApi,
                now: () => env.now,
                onState: (state) => states.push(state),
            });
            voiceCall.start();
            // Accueil demandé à Google : Ama « réfléchit » à l'écran tant que la voix n'a pas commencé.
            out.greetingWaiting = [...states];
            await env.answer(env.fetched[0]);
            env.startAudio();
            out.greetingSpeaking = states.at(-1);
            env.endAudio();
            env.advance(0);
            await tick();
            const mic = env.recognitions.at(-1);
            mic.onspeechstart?.();
            mic.onresult?.({ resultIndex: 0, results: [Object.assign([{ transcript: 'Combien coûte la formation ?' }], { isFinal: true })] });
            mic.onend?.();
            env.advance(2000);
            out.asking = states.at(-1);
            onText('Elle coûte 25 000 FCFA. ');
            finish();
            await tick();
            // Texte reçu, voix en préparation chez Google : toujours « réfléchit », la voix est demandée tout de suite.
            out.textReady = { state: states.at(-1), internal: voiceCall.state, fetched: env.fetched.length };
            env.advance(3000);
            out.stillThinking = states.at(-1);
            await env.answer('Elle coûte 25 000 FCFA.');
            env.startAudio();
            out.replySpeaking = states.at(-1);
            out.all = states;
            console.log(JSON.stringify(out));
            JS);

        $this->assertSame(['thinking'], $out['greetingWaiting']);
        $this->assertSame('speaking', $out['greetingSpeaking']);
        $this->assertSame('thinking', $out['asking']);
        $this->assertSame(['state' => 'thinking', 'internal' => 'speaking', 'fetched' => 2], $out['textReady']);
        $this->assertSame('thinking', $out['stillThinking']);
        $this->assertSame('speaking', $out['replySpeaking']);
        // « Parle » n'apparaît jamais avant le vrai début d'une voix.
        $this->assertSame(['thinking', 'speaking', 'listening', 'thinking', 'speaking'], $out['all']);
    }

    public function test_a_sound_that_never_ends_is_stopped_before_the_phone_voice_takes_over(): void
    {
        $out = $this->runNode(<<<'JS'
            const { createElementPlayer } = await import(__VOICE__);
            const listeners = {};
            const log = [];
            const element = {
                src: '', duration: 1, ended: false,
                addEventListener(name, callback) { listeners[name] = callback; },
                play() { log.push('lecture'); return Promise.resolve(); },
                pause() { log.push('pause'); listeners.pause?.(); },
            };
            const timers = [];
            const player = createElementPlayer(element, { setTimer: (callback) => { timers.push(callback); return timers.length; }, clearTimer: () => {} });
            player.play('blob:x', { onstart: () => log.push('début'), onend: () => log.push('fin'), onerror: () => log.push('voix du téléphone') });
            element.src = 'blob:x';
            listeners.playing();
            timers.at(-1)();
            out.log = log;
            console.log(JSON.stringify(out));
            JS);

        // Le son est mis en pause AVANT que la voix du téléphone parle, et la pause ne déclenche pas un second secours.
        $this->assertSame(['lecture', 'début', 'pause', 'voix du téléphone'], $out['log']);
    }
}
