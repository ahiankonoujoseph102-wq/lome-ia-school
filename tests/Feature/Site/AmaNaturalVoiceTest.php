<?php

namespace Tests\Feature\Site;

use App\Assistant\GeminiSpeech;
use App\Assistant\NaturalVoice;
use App\Models\AdminActivity;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantVoiceUsage;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\VoiceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Voix naturelle d'Ama (modèles « TTS » de Gemini) côté serveur : interrupteur éteint par défaut, audio fabriqué avec
 * la clé du serveur, voix du téléphone en secours (refus, délai dépassé, quota du jour épuisé, limite par minute du
 * site), mémoire des phrases écrites à l'avance, vie privée, compteurs et écran de l'admin. Faux Google (Http::fake) :
 * aucun vrai appel.
 */
class AmaNaturalVoiceTest extends TestCase
{
    use RefreshDatabase;

    private const string KEY = 'cle-de-test-GEMINI-voix-9z8y7x';

    private const string VISITOR = 'vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv';

    private const string PCM = "\x01\x00\x02\x00\x03\x00\x04\x00";

    /**
     * Réponse du faux Google (changée au fil d'un test).
     *
     * @var \Closure(): mixed
     */
    private \Closure $google;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->google = fn () => Http::response(['error' => ['message' => 'aucune réponse prévue']], 500);
        Http::fake(['generativelanguage.googleapis.com/*' => fn () => ($this->google)()]);
        Storage::fake('ama_voice');
        config(['services.gemini.key' => self::KEY, 'services.gemini.model' => 'modele-texte-de-test']);
    }

    /**
     * @param  array<string, mixed>  $natural
     */
    private function enable(array $natural = []): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'natural_voice' => ['enabled' => true, 'voice' => 'Sulafat', 'model' => 'gemini-3.8-flash-lite-tts', 'minute_limit' => 3, ...$natural],
        ]]);
    }

    private function fakeAudio(string $mime = 'audio/L16;codec=pcm;rate=24000', string $bytes = self::PCM): void
    {
        $this->google = fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => $mime, 'data' => base64_encode($bytes)]]]]]],
        ]);
    }

    private function voice(string $text, ?User $user = null): TestResponse
    {
        $request = $user !== null ? $this->actingAs($user) : $this;

        return $request->withCredentials()->withCookie('ama_visitor', self::VISITOR)->postJson(route('assistant.voice'), ['text' => $text]);
    }

    private function pageRequest(): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    private function assertFallback(TestResponse $response): void
    {
        $response->assertOk()->assertExactJson(['fallback' => true]);
    }

    public function test_the_natural_voice_is_off_by_default_and_everything_works_as_before(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);

        $this->assertFalse(app(NaturalVoice::class)->enabled());
        $this->assertNull(VoiceAssistant::browserConfig($this->pageRequest())['naturalVoice']);

        // Aucune demande à Google : le navigateur garde la voix du téléphone.
        $this->assertFallback($this->voice('Bonjour, je suis Ama.'));
        Http::assertNothingSent();

        // La page publique ne mentionne pas la voix naturelle dans la politique de confidentialité.
        $this->get(route('legal.privacy'))->assertOk()->assertDontSee('data-test="privacy-natural-voice"', false)
            ->assertSee('La lecture des réponses à voix haute est faite par votre navigateur.');
    }

    public function test_the_voice_is_made_by_the_server_with_its_key_and_only_the_text(): void
    {
        $this->enable();
        $this->fakeAudio();

        $config = VoiceAssistant::browserConfig($this->pageRequest());
        $this->assertSame(route('assistant.voice'), $config['naturalVoice']['endpoint']);
        $this->assertSame(10000, $config['naturalVoice']['timeoutMs']);
        // Réponse courte : une seule demande ; deux au-delà de 300 caractères par défaut (réglable dans l'admin).
        $this->assertSame(300, $config['naturalVoice']['splitLength']);
        $this->assertStringNotContainsString(self::KEY, (string) json_encode($config));

        $response = $this->voice('La formation coûte 25 000 FCFA.');

        $response->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        // Son brut (PCM) de Google complété par un en-tête WAV (24 000 Hz, 16 bits, mono).
        $this->assertSame(GeminiSpeech::wav(self::PCM, 24000), $response->getContent());
        $this->assertStringStartsWith('RIFF', (string) $response->getContent());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash-lite-tts:generateContent'
                && $request->header('x-goog-api-key') === [self::KEY]
                && ! str_contains($request->url(), self::KEY)
                && $request['contents'] === [['parts' => [['text' => 'La formation coûte 25 000 FCFA.']]]]
                && $request['generationConfig'] === [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => 'Sulafat']]],
                ]
                && ! isset($request['system_instruction']);
        });

        $this->assertSame(['spoken' => 1, 'cached' => 0, 'google_requests' => 1, 'refusals' => 0, 'fallbacks' => 0], AssistantVoiceUsage::today());
    }

    public function test_a_wav_file_from_gemini_3_8_is_served_as_is(): void
    {
        $this->enable();
        $wav = GeminiSpeech::wav("\x05\x00\x06\x00", 24000);
        $this->fakeAudio('audio/wav', $wav);

        $this->voice('Vous payez par Mobile Money.')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->assertSame($wav, GeminiSpeech::audio($wav, 'audio/wav')->bytes);
    }

    public function test_a_refusal_from_google_gives_the_phone_voice(): void
    {
        $this->enable();
        $this->google = fn () => Http::response(['error' => ['code' => 500, 'status' => 'INTERNAL', 'message' => 'Erreur interne']], 500);

        $this->assertFallback($this->voice('Vous payez par Mobile Money.'));

        $this->assertSame(['spoken' => 0, 'cached' => 0, 'google_requests' => 1, 'refusals' => 1, 'fallbacks' => 1], AssistantVoiceUsage::today());

        // Réponse sans audio : même chose.
        $this->google = fn () => Http::response(['candidates' => [['finishReason' => 'OTHER']]]);
        $this->travel(2)->minutes();
        $this->assertFallback($this->voice('Vous payez par Mobile Money.'));
        $this->assertSame(2, AssistantVoiceUsage::today()['refusals']);
    }

    public function test_a_delay_exceeded_gives_the_phone_voice(): void
    {
        $this->enable();
        $this->google = fn () => throw new ConnectionException('cURL error 28: Operation timed out after 8000 milliseconds');

        $this->assertFallback($this->voice('Vous payez par Mobile Money.'));
        $this->assertSame(1, AssistantVoiceUsage::today()['refusals']);
        $this->assertSame(1, AssistantVoiceUsage::today()['fallbacks']);
    }

    public function test_when_the_daily_quota_is_exhausted_the_site_stops_trying_until_the_next_google_day(): void
    {
        $this->enable();
        $this->travelTo(now()->setTimezone('America/Los_Angeles')->setTime(10, 0)->setTimezone('Africa/Lome'));
        $this->google = fn () => Http::response(['error' => [
            'code' => 429,
            'status' => 'RESOURCE_EXHAUSTED',
            'message' => 'You exceeded your current quota.',
            'details' => [['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaMetric' => 'generativelanguage.googleapis.com/generate_requests_per_model_per_day', 'quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]],
        ]], 429);

        $this->assertFallback($this->voice('Vous payez par Mobile Money.'));
        $this->assertTrue(NaturalVoice::dailyBlocked());

        // Plus aucun essai le même jour, même après la minute.
        $this->travel(3)->hours();
        $this->assertFallback($this->voice('Une autre phrase de la réponse.'));
        Http::assertSentCount(1);
        $this->assertSame(['spoken' => 0, 'cached' => 0, 'google_requests' => 1, 'refusals' => 1, 'fallbacks' => 2], AssistantVoiceUsage::today());

        // Nouvelle journée de Google (minuit, heure du Pacifique) : le site réessaie tout seul.
        $this->travelTo(now()->setTimezone('America/Los_Angeles')->addDay()->setTime(0, 5)->setTimezone('Africa/Lome'));
        $this->assertFalse(NaturalVoice::dailyBlocked());
        $this->fakeAudio();
        $this->voice('Une autre phrase de la réponse.')->assertOk()->assertHeader('Content-Type', 'audio/wav');
    }

    public function test_a_quota_per_minute_from_google_does_not_block_the_whole_day(): void
    {
        $this->enable();
        $this->google = fn () => Http::response(['error' => [
            'code' => 429,
            'status' => 'RESOURCE_EXHAUSTED',
            'message' => 'Quota exceeded.',
            'details' => [['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier']]]],
        ]], 429);

        $this->assertFallback($this->voice('Vous payez par Mobile Money.'));
        $this->assertFalse(NaturalVoice::dailyBlocked());
        $this->assertSame(1, AssistantVoiceUsage::today()['refusals']);
    }

    public function test_the_site_respects_its_own_limit_per_minute(): void
    {
        $this->enable(['minute_limit' => 3]);
        $this->fakeAudio();

        foreach (['Première phrase.', 'Deuxième phrase.', 'Troisième phrase.'] as $text) {
            $this->voice($text)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        }

        // Quatrième demande dans la même minute : voix du téléphone, sans demander à Google.
        $this->assertFallback($this->voice('Quatrième phrase.'));
        Http::assertSentCount(3);

        $this->travel(61)->seconds();
        $this->voice('Quatrième phrase.')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        Http::assertSentCount(4);
        $this->assertSame(1, AssistantVoiceUsage::today()['fallbacks']);
    }

    public function test_prewritten_sentences_are_made_once_then_kept(): void
    {
        $this->enable();
        $this->fakeAudio();
        $greeting = 'Bonsoir, je suis Ama, l\'assistante de Lomé IA School.';

        $first = $this->voice($greeting)->assertOk()->getContent();
        $this->travel(2)->minutes();
        $second = $this->voice($greeting)->assertOk()->assertHeader('Content-Type', 'audio/wav')->getContent();

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        $this->assertSame(['spoken' => 2, 'cached' => 1, 'google_requests' => 1, 'refusals' => 0, 'fallbacks' => 0], AssistantVoiceUsage::today());
        $this->assertCount(1, Storage::disk('ama_voice')->allFiles());

        // Même gardée, une phrase écrite à l'avance ne compte plus dans la limite par minute.
        foreach (range(1, 5) as $i) {
            $this->voice($greeting)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        }
        Http::assertSentCount(1);

        // Bulles, aides, demande de répéter, au revoir : reconnues comme écrites à l'avance.
        $voice = app(NaturalVoice::class);
        $this->assertTrue($voice->isPrewritten(VoiceAssistant::nudgeText('checkout')));
        $this->assertTrue($voice->isPrewritten('Excusez-moi, je n\'ai pas bien entendu.'));
        $this->assertTrue($voice->isPrewritten('Je ne vous entends plus.'));
        $this->assertTrue($voice->isPrewritten('Bonsoir ! Une question ?'));
        // Une réponse de Gemini n'est pas gardée.
        $this->assertFalse($voice->isPrewritten('La formation Agents IA coûte 25 000 FCFA.'));
    }

    public function test_the_first_name_of_the_visitor_is_never_sent_to_google(): void
    {
        $this->enable();
        $this->fakeAudio();
        $user = User::factory()->create(['name' => 'Kossi Mensah']);

        $this->voice('Bonsoir Kossi, je suis Ama, l\'assistante de Lomé IA School.', $user)->assertOk()->assertHeader('Content-Type', 'audio/wav');

        Http::assertSent(fn (Request $request): bool => $request['contents'][0]['parts'][0]['text'] === 'Bonsoir, je suis Ama, l\'assistante de Lomé IA School.');
        Http::assertNotSent(fn (Request $request): bool => str_contains(json_encode($request->data()) ?: '', 'Kossi'));
    }

    public function test_a_name_given_by_the_visitor_or_personal_data_is_read_by_the_phone(): void
    {
        $this->enable();
        $this->fakeAudio();

        // Le visiteur a donné son prénom à Ama (transmission au fondateur).
        $conversation = AssistantConversation::query()->create([
            'public_id' => 'conv-test-voix',
            'visitor_hash' => hash_hmac('sha256', self::VISITOR, (string) config('app.key')),
            'page' => 'accueil',
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create(['role' => AssistantMessage::VISITOR, 'content' => 'je m\'appelle afi']);

        $this->assertFallback($this->voice('Merci Afi, je transmets votre question.'));
        $this->assertFallback($this->voice('Écrivez-nous au 90 12 34 56.'));
        $this->assertFallback($this->voice('Notre adresse est contact@exemple.com.'));
        Http::assertNothingSent();
        $this->assertSame(3, AssistantVoiceUsage::today()['fallbacks']);

        // Les noms du site restent dits avec la voix naturelle.
        $this->voice('Merci, Coach Joseph vous répondra sur WhatsApp.')->assertOk()->assertHeader('Content-Type', 'audio/wav');
    }

    public function test_the_browser_reports_its_own_fallbacks(): void
    {
        $this->enable();

        $this->postJson(route('assistant.voice.fallback'))->assertOk();
        $this->postJson(route('assistant.voice.fallback'))->assertOk();

        $this->assertSame(2, AssistantVoiceUsage::today()['fallbacks']);
    }

    public function test_the_admin_screen_shows_the_switch_off_and_saves_the_choices(): void
    {
        $admin = User::factory()->admin()->create();
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))
            ->assertOk()
            ->assertSee('data-test="natural-voice"', false)
            ->assertSee('Voix naturelle')
            ->assertSee('gemini-3.8-flash-lite-tts')
            ->assertSee('Écouter')
            ->assertSee('Voix naturelles envoyées au téléphone')
            ->assertSee('Délai avant la voix du téléphone')
            ->assertSee('Refus du fournisseur')
            ->assertSee('Passages à la voix du téléphone')
            ->assertSee('Longueur à partir de laquelle une réponse est lue en deux demandes')
            ->assertDontSee(self::KEY);

        $this->assertFalse((bool) config('site.voice_assistant.natural_voice.enabled', false));

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'provider' => 'gemini',
            'natural_voice' => '1',
            'natural_voice_name' => 'Aoede',
            'natural_voice_model' => 'gemini-3.8-flash-tts',
            'natural_voice_minute_limit' => '2',
            'natural_voice_split_length' => '450',
            'natural_voice_timeout' => '12',
        ])->assertRedirect();

        // « Voix du téléphone en secours » absent du formulaire : allumé (valeur par défaut).
        // Aucun fournisseur choisi auparavant : Google (comme avant Cloudflare) ; limite et plafond de Cloudflare par défaut.
        $this->assertSame(['enabled' => true, 'provider' => 'gemini', 'voice' => 'Aoede', 'model' => 'gemini-3.8-flash-tts', 'minute_limit' => 2, 'split_length' => 450, 'timeout' => 12, 'phone_fallback' => true, 'cloudflare_minute_limit' => 20, 'daily_minutes' => 400], ((array) SiteSettings::get('site.voice_assistant'))['natural_voice']);
        $this->assertStringContainsString('voix naturelle : oui (Google, Aoede, gemini-3.8-flash-tts, 2 par minute, deux demandes au-delà de 450 caractères, voix du téléphone après 12 s)', (string) AdminActivity::query()->latest('id')->value('description'));
        // Délai transmis au navigateur ; le serveur attend Google au moins 2 s de plus.
        $this->assertSame(12000, NaturalVoice::timeoutMs());
        $this->assertSame(14, GeminiSpeech::timeoutSeconds());
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'gemini', 'natural_voice_timeout' => '2'])
            ->assertSessionHasErrors('natural_voice_timeout');

        // Longueur transmise au navigateur ; hors limites, refusée.
        $this->assertSame(450, NaturalVoice::splitLength());
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'gemini', 'natural_voice_split_length' => '20'])
            ->assertSessionHasErrors('natural_voice_split_length');

        // Voix inconnue refusée.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'gemini', 'natural_voice_name' => 'Puck'])
            ->assertSessionHasErrors('natural_voice_name');

        // La politique de confidentialité explique alors la voix d'Ama.
        $this->get(route('legal.privacy'))->assertOk()->assertSee('data-test="privacy-natural-voice"', false)
            ->assertSee('notre site envoie le texte de sa réponse (jamais vos messages) à Google');
    }

    public function test_the_listen_button_plays_the_sample_once_then_from_memory(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fakeAudio();

        $response = $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['voice' => 'Leda', 'model' => 'gemini-3.8-flash-lite-tts']);
        $response->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['voice' => 'Leda', 'model' => 'gemini-3.8-flash-lite-tts'])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['contents'][0]['parts'][0]['text'] === NaturalVoice::SAMPLE
            && $request['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] === 'Leda');

        // Refus de Google : message clair pour l'admin.
        $this->google = fn () => Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded per day']], 429);
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['voice' => 'Kore', 'model' => 'gemini-3.8-flash-lite-tts'])
            ->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'quota de voix du jour'));

        // Réservé à l'admin.
        $this->actingAs(User::factory()->create())->postJson(route('admin.settings.voice-preview'), ['voice' => 'Kore', 'model' => 'gemini-3.8-flash-lite-tts'])->assertForbidden();
    }

    public function test_the_admin_sees_todays_counters_and_the_daily_block(): void
    {
        $admin = User::factory()->admin()->create();
        $this->enable();
        AssistantVoiceUsage::bump('spoken', 'google_requests');
        AssistantVoiceUsage::bump('spoken', 'cached');
        AssistantVoiceUsage::bump('refusals', 'fallbacks');

        $this->google = fn () => Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded per day']], 429);
        $this->voice('Vous payez par Mobile Money.');

        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))
            ->assertOk()
            ->assertSeeInOrder(['data-test="natural-voice-spoken"', '>2<'], false)
            ->assertSeeInOrder(['data-test="natural-voice-refusals"', '>2<'], false)
            ->assertSeeInOrder(['data-test="natural-voice-fallbacks"', '>2<'], false)
            ->assertSee('data-test="natural-voice-daily-blocked"', false);
    }
}
