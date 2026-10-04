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
 * délai dépassé (4 s), lecture impossible, une seule voix par réponse, prénom jamais envoyé, et mode appel complet
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
        const { createNaturalSynth, NaturalUtterance, hidePrivateWords, splitForSpeech, NATURAL_TIMEOUT_MS } = await import(__VOICE__);
        const { createVoiceCall } = await import(__CALL__);
        const tick = async () => { for (let i = 0; i < 5; i += 1) await new Promise((resolve) => setImmediate(resolve)); };

        function setup({ privateWords = [] } = {}) {
            const env = { log: [], fetched: [], pending: [], aborted: [], playing: null, phone: null, phoneSaid: [], fallbacks: [], now: 0, timers: [], events: [] };
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
                onFallback: (reason) => env.fallbacks.push(reason),
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

    public function test_a_long_reply_is_split_and_played_with_the_natural_voice(): void
    {
        $out = $this->runNode(<<<'JS'
            {
                const env = setup();
                env.synth.speak(env.utterance('Phrase un. Phrase deux. Phrase trois.'));
                // Première phrase seule (démarrage rapide), puis le reste d'un bloc : deux demandes seulement.
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

    public function test_the_call_mode_uses_the_natural_voice_with_two_requests_and_keeps_interruption_and_hang_up(): void
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

            // 1. Accueil puis réponse en trois phrases : la première seule, les deux autres d'un bloc dès qu'elle commence.
            {
                const env = setup();
                let onText;
                let finish;
                const voiceCall = call(env, (text, options) => new Promise((resolve) => { onText = options.onText; finish = () => resolve({ reply: 'Elle coûte 25 000 FCFA. Vous payez par Mobile Money. Voulez-vous le lien ?', fallback: false }); }));
                voiceCall.start();
                out.greetingFetched = [...env.fetched];
                await env.answer(env.fetched[0]);
                env.startAudio();
                // Le reste de l'accueil est demandé d'un bloc pendant que la première phrase est lue.
                out.greetingRest = env.fetched.slice(1);
                await env.answer(env.fetched[1]);
                env.endAudio();
                await tick();
                env.startAudio();
                env.endAudio();
                await tick();
                out.afterGreeting = voiceCall.state;
                env.fetched = [];
                say(env, 'Combien coûte la formation ?');
                onText('Elle coûte 25 000 FCFA. Vous payez par Mobile Money. Voulez-vous le lien ? ');
                finish();
                await tick();
                out.replyFirstFetch = [...env.fetched];
                await env.answer('Elle coûte 25 000 FCFA.');
                env.startAudio();
                out.replyFetched = [...env.fetched];
                out.speaking = voiceCall.state;
                await env.answer('Vous payez par Mobile Money. Voulez-vous le lien ?');
                env.endAudio();
                await tick();
                out.secondAudio = env.playing?.url;
                env.startAudio();
                env.endAudio();
                env.advance(0);
                await tick();
                // La troisième phrase a été dite avec la deuxième : aucune nouvelle demande, micro rouvert.
                out.afterReply = { state: voiceCall.state, fetched: env.fetched.length, phone: env.phoneSaid };
                out.mouth = env.mouth;
                voiceCall.hangUp();
            }

            // 2. Interruption (toucher le cercle) pendant la voix naturelle, puis raccrocher.
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

        // Accueil : deux demandes (première phrase, puis la suite).
        $this->assertSame(['Bonjour, je suis Ama, l\'assistante de Lomé IA School.'], $out['greetingFetched']);
        $this->assertSame(['Je vous écoute.'], $out['greetingRest']);
        $this->assertSame('listening', $out['afterGreeting']);

        // Réponse : la première phrase seule, puis le reste d'un bloc dès qu'elle commence.
        $this->assertSame(['Elle coûte 25 000 FCFA.'], $out['replyFirstFetch']);
        $this->assertSame(['Elle coûte 25 000 FCFA.', 'Vous payez par Mobile Money. Voulez-vous le lien ?'], $out['replyFetched']);
        $this->assertSame('speaking', $out['speaking']);
        $this->assertSame('blob:Vous payez par Mobile Money. Voulez-vous le lien ?', $out['secondAudio']);
        $this->assertSame(['state' => 'listening', 'fetched' => 2, 'phone' => []], $out['afterReply']);
        // La bouche bouge pendant chaque audio.
        $this->assertContains('start', $out['mouth']);
        $this->assertContains('end', $out['mouth']);

        // Toucher le cercle coupe la voix naturelle et ouvre le micro ; raccrocher arrête tout.
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
                await env.answer('Phrase un.', 'refus');
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

        $this->assertSame(['phone' => ['Phrase un.', 'Phrase deux.'], 'fetched' => ['Phrase un.']], $out['sameAnswer']);
        $this->assertSame(['Phrase un.', 'Nouvelle réponse.'], $out['nextAnswer']);
    }

    public function test_the_page_sends_the_natural_voice_settings_only_when_it_is_on(): void
    {
        config(['services.gemini.key' => 'cle-de-test-page-voix']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);

        $this->get(route('home'))->assertOk()->assertDontSee('fallbackEndpoint', false)->assertDontSee('cle-de-test-page-voix');

        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', 'natural_voice' => ['enabled' => true]]]);

        $this->get(route('home'))->assertOk()->assertSee('fallbackEndpoint', false)->assertDontSee('cle-de-test-page-voix');
    }
}
