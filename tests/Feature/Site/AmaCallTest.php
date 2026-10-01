<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaPrompt;
use App\Enums\AssistantIncidentType;
use App\Models\AssistantConversation;
use App\Models\AssistantIncident;
use App\Models\AssistantMessage;
use App\Settings\SiteSettings;
use App\Support\VoiceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Mode appel d'Ama (« Parler avec Ama ») : réponse de Gemini en continu (serveur), écran d'appel (page), et boucle
 * écoute → envoi → lecture → réouverture du micro, interruption, fin après silence et limites, exécutée avec Node.js
 * et de fausses fonctions vocales (resources/js/ama-call.js). Faux Gemini (Http::fake) : aucun vrai appel.
 */
class AmaCallTest extends TestCase
{
    use RefreshDatabase;

    private const string KEY = 'cle-de-test-GEMINI-appel-1a2b3c';

    private const string VISITOR = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.gemini.key' => self::KEY, 'services.gemini.model' => 'modele-flash-de-test']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);
    }

    /**
     * Réponse de Google en continu (format SSE), un événement par morceau.
     *
     * @param  list<string>  $chunks
     */
    private function fakeStream(array $chunks): void
    {
        $body = '';
        foreach ($chunks as $chunk) {
            $body .= 'data: '.json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $chunk]]]]]])."\r\n\r\n";
        }

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return list<array<string, mixed>>
     */
    private function speak(string $message, string $visitor = self::VISITOR, array $extra = []): array
    {
        $response = $this->withCredentials()->withCookie('ama_visitor', $visitor)
            ->post(route('assistant.call'), ['message' => $message, ...$extra], ['Accept' => 'text/event-stream']);

        // Format « text/event-stream » : transmis sans attente par Nginx et Cloudflare.
        $response->assertOk()->assertHeader('X-Accel-Buffering', 'no');
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $content = $response->streamedContent();
        $this->assertStringNotContainsString(self::KEY, $content);
        // Une ligne de commentaire ouvre la connexion tout de suite, avant la réponse de Gemini.
        $this->assertStringStartsWith(": ama\n\n", $content);

        $lines = array_filter(explode("\n", $content), fn (string $line): bool => str_starts_with($line, 'data: '));

        return array_map(fn (string $line): array => json_decode(substr($line, 6), true), array_values($lines));
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function spokenText(array $events): string
    {
        return implode('', array_map(fn (array $event): string => $event['text'], array_filter($events, fn (array $event): bool => $event['type'] === 'text')));
    }

    public function test_the_reply_is_streamed_piece_by_piece_and_stored(): void
    {
        $this->fakeStream(['La formation coûte ', '25 000 FCFA. Vous pouvez ', 'payer par **Mobile Money**. ', 'Voulez-vous savoir comment ?']);

        $events = $this->speak('Combien coûte la formation ?');
        $done = array_pop($events);

        // Plusieurs morceaux avant la fin : Ama commence à parler sans attendre la réponse complète.
        $this->assertGreaterThanOrEqual(2, count($events));
        $this->assertSame('done', $done['type']);
        $this->assertFalse($done['fallback']);
        $this->assertSame('La formation coûte 25 000 FCFA. Vous pouvez payer par Mobile Money. Voulez-vous savoir comment ?', $done['reply']);
        $this->assertSame($done['reply'], $this->spokenText($events));

        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), 'models/modele-flash-de-test:streamGenerateContent?alt=sse')
                && $request->hasHeader('x-goog-api-key', self::KEY)
                && ! str_contains($request->url(), self::KEY)
                && str_contains($body['system_instruction']['parts'][0]['text'], 'Mode appel : la personne te parle à voix haute');
        });

        $conversation = AssistantConversation::query()->sole();
        $this->assertSame($done['conversation'], $conversation->public_id);
        $this->assertTrue($conversation->voice_used);
        $this->assertSame([AssistantMessage::VISITOR, AssistantMessage::AMA], $conversation->messages()->orderBy('id')->pluck('role')->all());
        $this->assertSame($done['reply'], AssistantMessage::query()->where('role', AssistantMessage::AMA)->value('content'));
    }

    public function test_the_unknown_marker_is_never_spoken_and_the_question_is_flagged(): void
    {
        $this->fakeStream(['[INCO', 'NNU] Je n\'ai pas ', 'cette information. Je vous propose la consultation gratuite.']);

        $events = $this->speak('Quel est le nom du chat de Joseph ?');
        $done = array_pop($events);

        $this->assertStringNotContainsString('INCONNU', $this->spokenText($events).$done['reply']);
        $this->assertStringStartsWith('Je n\'ai pas cette information.', $this->spokenText($events));
        $this->assertTrue((bool) AssistantMessage::query()->where('role', AssistantMessage::AMA)->value('unanswered'));
    }

    public function test_each_spoken_sentence_counts_as_a_message_and_a_limit_ends_the_call(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', 'visitor_daily_limit' => 2]]);
        $this->fakeStream(['Bonjour, je vous écoute. ']);

        $this->speak('Bonjour');
        $this->speak('Une autre question');
        $events = $this->speak('Encore une question');

        // Limite atteinte : un seul événement, message clair, bouton WhatsApp, Gemini pas appelé.
        $this->assertCount(1, $events);
        $this->assertTrue($events[0]['fallback']);
        $this->assertNotEmpty($events[0]['whatsapp']);
        $this->assertSame(AssistantIncidentType::VisitorDailyLimit->visitorMessage(), $events[0]['reply']);
        Http::assertSentCount(2);
        $this->assertSame(2, AssistantMessage::query()->where('role', AssistantMessage::VISITOR)->count());
    }

    public function test_a_google_quota_gives_a_clear_spoken_message(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota']], 429)]);

        $events = $this->speak('Bonjour');

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]['fallback']);
        $this->assertSame(AssistantIncidentType::GeminiQuota->visitorMessage(), $events[0]['reply']);
        $this->assertTrue(AssistantIncident::query()->where('type', AssistantIncidentType::GeminiQuota)->exists());
    }

    public function test_an_empty_or_too_long_sentence_is_answered_without_calling_gemini(): void
    {
        Http::fake();

        $this->assertSame(AmaPrompt::MISHEARD_REPLY, $this->speak('  ')[0]['notice']);
        $this->assertSame('Votre question est un peu longue. Pouvez-vous la dire en plus court ?', $this->speak(str_repeat('a', 501))[0]['notice']);
        Http::assertNothingSent();
    }

    public function test_the_written_chat_still_gets_a_whole_reply(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'Bonjour !']]]]]])]);

        $this->withCookie('ama_visitor', self::VISITOR)->postJson(route('assistant.message'), ['message' => 'Bonjour'])
            ->assertOk()
            ->assertJson(['reply' => 'Bonjour !', 'fallback' => false]);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), ':generateContent')
            && ! str_contains(json_decode($request->body(), true)['system_instruction']['parts'][0]['text'], 'Mode appel'));
    }

    public function test_the_call_screen_is_on_the_page(): void
    {
        $html = (string) $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-test="ama-call"', false)
            ->assertSee('data-test="ama-call-button"', false)
            ->assertSee('data-test="ama-call-offer"', false)
            ->assertSee('Parler avec Ama')
            ->assertSee('Raccrocher')
            ->assertSee('Appel terminé')
            ->getContent();

        $this->assertMatchesRegularExpression('#localhost:8000\\\\*/assistante\\\\*/appel#', $html);

        // Deux boutons à côté du champ : le micro (dictée, s'il sait écouter) puis le téléphone (s'il sait aussi lire).
        $microphone = (int) strpos($html, 'data-test="ama-microphone"');
        $phone = (int) strpos($html, 'data-test="ama-call-button"');
        $this->assertGreaterThan(0, $microphone);
        $this->assertGreaterThan($microphone, $phone);
        $this->assertStringContainsString('x-show="canListen"', $html);
        $this->assertStringContainsString('x-show="canCall"', $html);
        $this->assertStringNotContainsString('canListen && ! canCall', $html);
        $this->assertStringNotContainsString(self::KEY, $html);
    }

    /**
     * L'avertissement sous le champ est retiré, mais le comportement d'Ama ne change pas : à l'écrit comme en
     * appel, elle dit qu'elle est une IA quand on le lui demande et refuse les codes (Mobile Money, mot de passe…).
     */
    public function test_ama_still_says_she_is_an_ai_and_refuses_codes_without_the_warning_line(): void
    {
        foreach ([false, true] as $call) {
            $prompt = AmaPrompt::build([], call: $call);

            $this->assertStringContainsString('Si on te demande si tu es un humain ou une IA, tu dis clairement que tu es une assistante IA.', $prompt);
            $this->assertStringContainsString('Tu ne demandes jamais de code secret, de mot de passe, de code Mobile Money', $prompt);
            $this->assertStringContainsString('tu lui dis de ne jamais le partager, même avec l\'école', $prompt);
        }

        $this->get(route('home'))->assertOk()->assertDontSee('Ama est une IA et peut se tromper');
    }

    public function test_the_call_mode_is_absent_with_the_elevenlabs_agent(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => VoiceAssistant::ELEVENLABS, 'agent_id' => 'agent_9901m3phrpdwedt88vsdqmnwk09z']]);

        $this->get(route('home'))->assertOk()->assertDontSee('data-test="ama-call"', false)->assertDontSee('assistante/appel');
    }

    public function test_the_call_only_starts_when_the_visitor_touches_its_button(): void
    {
        $script = (string) file_get_contents(resource_path('js/site.js'));
        $partial = (string) file_get_contents(resource_path('views/site/partials/voice-assistant.blade.php'));
        $engine = (string) file_get_contents(resource_path('js/ama-call.js'));

        // startCall() n'est appelé que par les deux boutons « Parler avec Ama », jamais par le code lui-même.
        $this->assertSame(2, substr_count($partial, '@click="startCall()"'));
        $this->assertSame(2, substr_count($partial, 'startCall()'));
        $this->assertStringNotContainsString('this.startCall(', $script);
        // Le moteur n'ouvre le micro qu'à un seul endroit (listen, jamais avant start()).
        $this->assertSame(1, substr_count($engine, '.start()'));
        $this->assertStringNotContainsString('getUserMedia', $engine.$script);
        // Le micro se coupe en quittant la page ou en passant à une autre application.
        $this->assertStringContainsString("addEventListener('pagehide', () => this.hangUp())", $script);
        // Gemini n'est jamais appelé par le navigateur.
        $this->assertStringNotContainsString('generativelanguage', $engine.$script);
    }

    /**
     * Boucle de l'appel avec de fausses fonctions vocales (reconnaissance, synthèse, minuteries) et un faux serveur.
     */
    public function test_the_call_loop_with_fake_voice_functions(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama-call.js.');
        }

        $module = json_encode('file://'.resource_path('js/ama-call.js'));
        $program = <<<JS
            const { createVoiceCall, CALL_GREETING, CALL_GOODBYE, CALL_ERROR, PAUSE_MS } = await import({$module});
            const tick = () => new Promise((resolve) => setImmediate(resolve));

            function setup(askImpl) {
                const env = { log: [], recognitions: [], spoken: [], current: null, now: 0, timers: [], states: [], ends: [], asked: [], visitor: [], ama: [] };
                env.Recognition = function () {
                    this.start = () => { env.log.push('micro ouvert'); };
                    this.stop = () => { this.onend?.(); };
                    this.abort = () => { this.aborted = true; env.log.push('micro coupé'); };
                    env.recognitions.push(this);
                };
                env.mic = () => env.recognitions.at(-1);
                env.say = (text) => {
                    const mic = env.mic();
                    mic.onspeechstart?.();
                    mic.onresult?.({ resultIndex: 0, results: [Object.assign([{ transcript: text }], { isFinal: true })] });
                    mic.onend?.();
                };
                // Phrase dite puis silence : la phrase part après le délai de silence (PAUSE_MS).
                env.sayDone = (text) => { env.say(text); env.advance(PAUSE_MS); };
                env.synth = {
                    speak(utterance) { env.current = utterance; env.spoken.push(utterance); env.log.push('dit : ' + utterance.text); },
                    cancel() { env.current = null; env.log.push('voix arrêtée'); },
                    getVoices: () => [{ lang: 'fr-FR', name: 'Thomas' }, { lang: 'fr-FR', name: 'Microsoft Denise Online (Natural)' }, { lang: 'en-US', name: 'Samantha' }],
                };
                env.finishSentence = () => { const utterance = env.current; env.current = null; utterance?.onend?.(); };
                env.finishAll = () => { while (env.current) env.finishSentence(); };
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
                env.call = createVoiceCall({
                    Recognition: env.Recognition,
                    synth: env.synth,
                    Utterance: function (text) { this.text = text; },
                    ask: (text, options) => { env.asked.push(text); return askImpl(text, options, env); },
                    timers: env.timersApi,
                    onState: (state) => env.states.push(state),
                    onVisitor: (text) => env.visitor.push(text),
                    onAmaText: (text, final) => env.ama.push([text, final === true]),
                    onEnd: (reason) => env.ends.push(reason),
                });
                return env;
            }

            const out = {};

            // 1. Boucle complète : accueil, écoute, envoi, lecture dès la première phrase, réouverture du micro.
            {
                let finish;
                const env = setup((text, { onText }) => new Promise((resolve) => { finish = () => resolve({ reply: 'Elle coûte 25 000 FCFA. Vous payez par Mobile Money.', fallback: false }); env.onText = onText; }));
                out.beforeStart = { mics: env.recognitions.length, state: env.call.state };
                env.call.start();
                out.greeting = { text: env.current?.text, state: env.call.state, mics: env.recognitions.length, rate: env.current?.rate, voice: env.current?.voice?.name, lang: env.current?.lang };
                env.finishAll();
                out.afterGreeting = env.call.state;
                env.sayDone('Combien coûte la formation ?');
                out.sent = { asked: env.asked, state: env.call.state, visitor: env.visitor };
                env.onText('Elle coûte 25 000 FCFA. Vous');
                out.firstSentence = { text: env.current?.text, state: env.call.state, stillWaiting: true };
                env.onText(' payez par Mobile Money.');
                finish();
                await tick();
                env.finishSentence();
                out.secondSentence = env.current?.text;
                env.finishSentence();
                out.afterReply = { state: env.call.state, mics: env.recognitions.length, final: env.ama.at(-1) };
                env.call.hangUp();
            }

            // 2. Interruption : toucher le cercle pendant qu'Ama parle.
            {
                const env = setup((text, { onText }) => { onText('Voici une longue réponse. Avec plusieurs phrases. '); return new Promise(() => {}); });
                env.call.start();
                env.finishAll();
                env.sayDone('Parle-moi des formations');
                out.interruptBefore = env.call.state;
                const mics = env.recognitions.length;
                env.call.touch();
                out.interrupt = { state: env.call.state, stopped: env.log.includes('voix arrêtée'), newMic: env.recognitions.length === mics + 1, speaking: env.current };
                // Une phrase de l'ancien tour qui se terminerait après coup ne relance rien.
                env.call.hangUp();
            }

            // 3. Silence : le micro se rouvre tout seul, le silence est compté ; parler remet le compteur à zéro ;
            //    après 20 secondes sans rien dire, au revoir puis fin de l'appel, micro coupé.
            {
                const env = setup(() => new Promise(() => {}));
                env.call.start();
                env.finishAll();
                env.mic().onend(); // le navigateur s'arrête seul sans rien entendre
                env.advance(300);
                out.reopened = { state: env.call.state, mics: env.recognitions.length };
                env.advance(15000);
                env.mic().onspeechstart();
                env.advance(15000);
                out.stillListening = env.call.state;
                env.advance(5100);
                out.goodbye = { text: env.current?.text, state: env.call.state, micCut: env.recognitions.every((mic) => mic.aborted || mic !== env.mic()) };
                env.finishAll();
                out.silenceEnd = { state: env.call.state, ends: env.ends, mics: env.recognitions.length };
            }

            // 4. Limite atteinte : Ama la dit, puis l'appel se termine (bouton WhatsApp affiché par la page).
            {
                const env = setup(() => Promise.resolve({ reply: 'Vous avez posé beaucoup de questions aujourd\'hui. Notre équipe vous répond sur WhatsApp.', fallback: true }));
                env.call.start();
                env.finishAll();
                env.sayDone('Encore une question');
                await tick();
                out.limit = { text: env.current?.text, state: env.call.state };
                env.finishAll();
                out.limitEnd = { state: env.call.state, ends: env.ends, micOpen: env.recognitions.length, final: env.ama.at(-1) };
            }

            // 5. Serveur injoignable : message clair lu, fin de l'appel.
            {
                const env = setup(() => Promise.resolve(null));
                env.call.start();
                env.finishAll();
                env.sayDone('Bonjour');
                await tick();
                out.error = env.current?.text;
                env.finishAll();
                out.errorEnd = env.ends;
            }

            // 6. Réponse reçue d'un bloc (hébergement qui ne transmet pas en continu) : lue phrase par phrase.
            {
                const env = setup(() => Promise.resolve({ reply: 'Première phrase. Deuxième phrase !', fallback: false }));
                env.call.start();
                env.finishAll();
                env.sayDone('Bonjour');
                await tick();
                const first = env.current?.text;
                env.finishSentence();
                out.whole = [first, env.current?.text];
                env.finishSentence();
                out.wholeAfter = env.call.state;
                env.call.hangUp();
            }

            // 7. Raccrocher : micro coupé, voix arrêtée, plus rien ne se relance.
            {
                const env = setup(() => new Promise(() => {}));
                env.call.start();
                env.finishAll();
                const mic = env.mic();
                const spokenBefore = env.spoken.length;
                env.call.hangUp();
                mic.onend?.();
                env.advance(60000);
                out.hangUp = { state: env.call.state, aborted: mic.aborted === true, ends: env.ends, mics: env.recognitions.length, spokeAgain: env.spoken.length !== spokenBefore };
            }

            // 8. Micro refusé : l'appel se termine sans boucle.
            {
                const env = setup(() => new Promise(() => {}));
                env.call.start();
                env.finishAll();
                env.mic().onerror({ error: 'not-allowed' });
                env.mic().onend?.();
                env.advance(60000);
                out.denied = { ends: env.ends, mics: env.recognitions.length };
            }

            // 9. Le visiteur touche le cercle pendant l'accueil : le micro s'ouvre tout de suite.
            {
                const env = setup(() => new Promise(() => {}));
                env.call.start();
                env.call.touch();
                out.skipGreeting = { state: env.call.state, mics: env.recognitions.length };
                env.call.hangUp();
            }

            out.texts = { CALL_GREETING, CALL_GOODBYE, CALL_ERROR };
            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        // 1. Aucun micro avant le toucher du bouton ; accueil lu avec la voix choisie, un peu plus lentement.
        $this->assertSame(['mics' => 0, 'state' => 'idle'], $out['beforeStart']);
        $this->assertSame(['text' => 'Bonjour, je suis Ama, l\'assistante de Lomé IA School.', 'state' => 'speaking', 'mics' => 0, 'rate' => 0.92, 'voice' => 'Microsoft Denise Online (Natural)', 'lang' => 'fr-FR'], $out['greeting']);
        $this->assertSame('listening', $out['afterGreeting']);
        $this->assertSame(['asked' => ['Combien coûte la formation ?'], 'state' => 'thinking', 'visitor' => ['Combien coûte la formation ?']], $out['sent']);
        // La première phrase est lue avant la fin de la réponse.
        $this->assertSame(['text' => 'Elle coûte 25 000 FCFA.', 'state' => 'speaking', 'stillWaiting' => true], $out['firstSentence']);
        $this->assertSame('Vous payez par Mobile Money.', $out['secondSentence']);
        // Micro rouvert une fois pendant la pause (le navigateur l'avait fermé), puis après la réponse.
        $this->assertSame(['state' => 'listening', 'mics' => 3, 'final' => ['Elle coûte 25 000 FCFA. Vous payez par Mobile Money.', true]], $out['afterReply']);

        // 2. Interruption.
        $this->assertSame('speaking', $out['interruptBefore']);
        $this->assertSame(['state' => 'listening', 'stopped' => true, 'newMic' => true, 'speaking' => null], $out['interrupt']);

        // 3. Silence.
        $this->assertSame(['state' => 'listening', 'mics' => 2], $out['reopened']);
        $this->assertSame('listening', $out['stillListening']);
        $this->assertSame('Je ne vous entends plus.', $out['goodbye']['text']);
        $this->assertTrue($out['goodbye']['micCut']);
        $this->assertSame('speaking', $out['goodbye']['state']);
        $this->assertSame(['state' => 'ended', 'ends' => ['silence'], 'mics' => 2], $out['silenceEnd']);

        // 4. Limite.
        $this->assertSame(['text' => 'Vous avez posé beaucoup de questions aujourd\'hui.', 'state' => 'speaking'], $out['limit']);
        $this->assertSame('ended', $out['limitEnd']['state']);
        $this->assertSame(['limit'], $out['limitEnd']['ends']);
        // Un micro pour écouter, rouvert une fois pendant la pause, et aucun après la limite.
        $this->assertSame(2, $out['limitEnd']['micOpen'], 'Le micro ne se rouvre pas après une limite.');
        $this->assertTrue($out['limitEnd']['final'][1]);

        // 5. Erreur.
        $this->assertSame('Ama ne peut pas répondre pour le moment.', $out['error']);
        $this->assertSame(['error'], $out['errorEnd']);

        // 6. Réponse d'un bloc.
        $this->assertSame(['Première phrase.', 'Deuxième phrase !'], $out['whole']);
        $this->assertSame('listening', $out['wholeAfter']);

        // 7. Raccrocher.
        $this->assertSame(['state' => 'ended', 'aborted' => true, 'ends' => ['hangup'], 'mics' => 1, 'spokeAgain' => false], $out['hangUp']);

        // 8. Micro refusé.
        $this->assertSame(['ends' => ['denied'], 'mics' => 1], $out['denied']);

        // 9. Accueil coupé.
        $this->assertSame(['state' => 'listening', 'mics' => 1], $out['skipGreeting']);
    }

    /**
     * Voix, découpage en phrases et lecture ligne par ligne (resources/js/ama.js).
     */
    public function test_voice_choice_sentences_and_line_reader(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama.js.');
        }

        $module = json_encode('file://'.resource_path('js/ama.js'));
        $program = <<<JS
            const { pickFrenchVoice, createSentenceSplitter, createLineReader } = await import({$module});
            const out = {};
            const name = (voices) => pickFrenchVoice(voices)?.name ?? null;

            // Android (Chrome) : voix Google, féminine de préférence.
            out.android = name([{ lang: 'fr-FR', name: 'fr-fr-x-frd-local' }, { lang: 'fr-FR', name: 'fr-fr-x-frc-local' }, { lang: 'en-US', name: 'en' }]);
            // Windows (Edge) : voix « Natural » en ligne.
            out.windows = name([{ lang: 'fr-FR', name: 'Microsoft Paul - French (France)', localService: true }, { lang: 'fr-FR', name: 'Microsoft Denise Online (Natural) - French (France)' }, { lang: 'fr-FR', name: 'Microsoft Henri Online (Natural) - French (France)' }]);
            // iPhone : Amélie (Canada) plutôt que Thomas.
            out.iphone = name([{ lang: 'fr-FR', name: 'Thomas' }, { lang: 'fr-CA', name: 'Amélie' }]);
            out.chrome = name([{ lang: 'fr-FR', name: 'Google français' }, { lang: 'fr-FR', name: 'Thomas', localService: true }]);
            out.none = name([{ lang: 'en-US', name: 'Samantha' }]);

            const splitter = createSentenceSplitter();
            out.sentences = [
                splitter.push('Bonjour ! Je suis'),
                splitter.push(' Ama. La formation coûte 25 000 FCFA.'),
                splitter.push(' Touchez « Acheter maintenant ».\\n• Étape 1'),
                splitter.flush(),
            ];

            const events = [];
            const reader = createLineReader((event) => events.push(event));
            reader.push('{"type":"text","te');
            reader.push('xt":"Bon"}\\n{"type":"text","text":"jour"}\\n{cassé}\\n{"type":"do');
            reader.push('ne","reply":"Bonjour"}');
            reader.end();
            out.events = events;

            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame('fr-fr-x-frc-local', $out['android']);
        $this->assertSame('Microsoft Denise Online (Natural) - French (France)', $out['windows']);
        $this->assertSame('Amélie', $out['iphone']);
        $this->assertSame('Google français', $out['chrome']);
        $this->assertNull($out['none']);
        $this->assertSame([['Bonjour !'], ['Je suis Ama.'], ['La formation coûte 25 000 FCFA.', 'Touchez « Acheter maintenant ».'], ['• Étape 1']], $out['sentences']);
        $this->assertSame([['type' => 'text', 'text' => 'Bon'], ['type' => 'text', 'text' => 'jour'], ['type' => 'done', 'reply' => 'Bonjour']], $out['events']);
    }
}
