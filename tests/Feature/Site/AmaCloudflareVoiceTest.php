<?php

namespace Tests\Feature\Site;

use App\Assistant\AudioDuration;
use App\Assistant\CloudflareSpeech;
use App\Assistant\NaturalVoice;
use App\Assistant\SpeechAudio;
use App\Assistant\SpeechFailure;
use App\Models\AdminActivity;
use App\Models\AssistantVoiceEvent;
use App\Models\AssistantVoiceQuota;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Voix naturelle d'Ama avec Cloudflare Workers AI (MeloTTS, en français) : audio fabriqué par le serveur avec ses
 * clés, voix du téléphone en secours (refus, délai dépassé, offre du jour épuisée, clé absente, plafond de minutes du
 * site), minutes d'audio comptées, limite par minute séparée de celle de Google, mémoire séparée par fournisseur, vie
 * privée, écran de l'admin. Faux Cloudflare (Http::fake) : aucun vrai appel.
 */
class AmaCloudflareVoiceTest extends TestCase
{
    use RefreshDatabase;

    private const string ACCOUNT = 'compte0123456789abcdefTEST';

    private const string TOKEN = 'jeton-de-test-CLOUDFLARE-9z8y7x';

    private const string VISITOR = 'vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv';

    private const string URL = 'https://api.cloudflare.com/client/v4/accounts/'.self::ACCOUNT.'/ai/run/@cf/myshell-ai/melotts';

    /**
     * Réponse du faux Cloudflare (changée au fil d'un test).
     *
     * @var \Closure(): mixed
     */
    private \Closure $cloudflare;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->cloudflare = fn () => Http::response(['success' => false, 'errors' => [['code' => 5000, 'message' => 'aucune réponse prévue']]], 500);
        Http::fake([
            'api.cloudflare.com/*' => fn () => ($this->cloudflare)(),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/wav', 'data' => base64_encode('RIFF'.str_repeat("\x00", 40))]]]]]]]),
        ]);
        Storage::fake('ama_voice');
        config([
            'services.gemini.key' => 'cle-gemini-de-test',
            'services.gemini.model' => 'modele-texte-de-test',
            'services.cloudflare.account_id' => self::ACCOUNT,
            'services.cloudflare.api_token' => self::TOKEN,
        ]);
    }

    /**
     * @param  array<string, mixed>  $natural
     */
    private function enable(array $natural = []): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'natural_voice' => ['enabled' => true, 'provider' => 'cloudflare', 'voice' => 'Kore', 'model' => 'gemini-3.8-flash-lite-tts', 'minute_limit' => 3, ...$natural],
        ]]);
    }

    /**
     * Faux MP3 : trames MPEG 1 couche III à 32 kbit/s et 44 100 Hz (104 octets et 1 152 échantillons chacune).
     */
    private static function mp3(float $seconds): string
    {
        $frames = (int) ceil($seconds * 44100 / 1152);

        return str_repeat("\xFF\xFB\x10\x64".str_repeat("\x00", 100), $frames);
    }

    private function fakeAudio(float $seconds = 3.0, bool $binary = false): void
    {
        $bytes = self::mp3($seconds);
        $this->cloudflare = $binary
            ? fn () => Http::response($bytes, 200, ['Content-Type' => 'audio/mpeg'])
            : fn () => Http::response(['result' => ['audio' => base64_encode($bytes)], 'success' => true, 'errors' => [], 'messages' => []]);
    }

    private function voice(string $text, ?User $user = null): TestResponse
    {
        $request = $user !== null ? $this->actingAs($user) : $this;

        return $request->withCredentials()->withCookie('ama_visitor', self::VISITOR)->postJson(route('assistant.voice'), ['text' => $text]);
    }

    private function assertFallback(TestResponse $response): void
    {
        $response->assertOk()->assertExactJson(['fallback' => true]);
    }

    private function lastFallbackCause(): ?string
    {
        return AssistantVoiceEvent::query()->where('kind', AssistantVoiceEvent::FALLBACK)->latest('id')->value('cause');
    }

    public function test_the_voice_is_made_by_cloudflare_on_the_server_with_only_the_text_in_french(): void
    {
        $this->enable();
        $this->fakeAudio();

        $response = $this->voice('Vous pouvez payer par Mobile Money depuis votre téléphone.');

        $response->assertOk()->assertHeader('Content-Type', 'audio/mpeg')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith("\xFF\xFB", $response->getContent());
        $this->assertStringNotContainsString(self::TOKEN, (string) $response->getContent());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && $request->data() === ['prompt' => 'Vous pouvez payer par Mobile Money depuis votre téléphone.', 'lang' => 'fr']);

        // Demande mesurée avec son fournisseur ; minutes d'audio comptées (3 s mesurées dans le fichier).
        $event = AssistantVoiceEvent::query()->where('kind', AssistantVoiceEvent::GOOGLE)->sole();
        $this->assertSame('cloudflare', $event->provider);
        $this->assertNull($event->cause);
        $this->assertEqualsWithDelta(3000, AssistantVoiceQuota::usedMs('cloudflare'), 30);
        $this->assertSame(1, AssistantVoiceQuota::requestsToday('cloudflare'));
    }

    public function test_a_binary_mp3_answer_is_accepted_too(): void
    {
        $this->enable();
        $this->fakeAudio(2.0, binary: true);

        $this->voice('Une réponse courte.')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertEqualsWithDelta(2000, AssistantVoiceQuota::usedMs('cloudflare'), 30);
    }

    public function test_the_natural_voice_page_settings_and_privacy_name_cloudflare(): void
    {
        $this->enable();

        $this->assertTrue(app(NaturalVoice::class)->enabled());
        $this->get(route('home'))->assertOk()->assertSee('fallbackEndpoint', false)->assertDontSee(self::TOKEN)->assertDontSee(self::ACCOUNT);

        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('data-test="privacy-natural-voice"', false)
            ->assertSee('à Cloudflare, qui en fabrique la voix')
            ->assertDontSee('à Google, qui en fabrique la voix');

        // Google choisi : le paragraphe nomme Google.
        $this->enable(['provider' => 'gemini']);
        $this->get(route('legal.privacy'))->assertOk()->assertSee('à Google, qui en fabrique la voix')->assertDontSee('à Cloudflare, qui en fabrique la voix');
    }

    public function test_a_refusal_from_cloudflare_gives_the_phone_voice(): void
    {
        $this->enable();
        $this->cloudflare = fn () => Http::response(['success' => false, 'errors' => [['code' => 5007, 'message' => 'No such model']]], 400);

        $this->assertFallback($this->voice('Une phrase de la réponse.'));

        $this->assertSame('cloudflare_error', $this->lastFallbackCause());
        $this->assertSame(0, AssistantVoiceQuota::usedMs('cloudflare'));
        $this->assertFalse(NaturalVoice::dailyBlocked('cloudflare'));
    }

    public function test_a_delay_exceeded_gives_the_phone_voice(): void
    {
        $this->enable();
        $this->cloudflare = fn () => throw new ConnectionException('Operation timed out after 12000 milliseconds for '.self::URL);

        $this->assertFallback($this->voice('Une phrase de la réponse.'));

        $this->assertSame('cloudflare_timeout', $this->lastFallbackCause());
        // Le serveur attend Cloudflare au moins 2 secondes de plus que le délai du téléphone (10 s par défaut).
        $this->assertSame(12, CloudflareSpeech::timeoutSeconds());
    }

    public function test_the_secrets_never_appear_in_an_error_message(): void
    {
        $this->cloudflare = fn () => throw new ConnectionException('Échec pour '.self::URL.' avec Bearer '.self::TOKEN);

        try {
            app(CloudflareSpeech::class)->synthesize('Bonjour');
            $this->fail('Un échec était attendu.');
        } catch (SpeechFailure $failure) {
            $this->assertTrue($failure->timeout);
            $this->assertStringNotContainsString(self::TOKEN, $failure->getMessage());
            $this->assertStringNotContainsString(self::ACCOUNT, $failure->getMessage());
            $this->assertStringContainsString('[clé]', $failure->getMessage());
        }
    }

    public function test_when_the_free_daily_allocation_is_used_up_the_site_stops_trying_until_midnight(): void
    {
        $this->enable();
        $this->travelTo(now()->utc()->setTime(15, 0));
        $this->cloudflare = fn () => Http::response(['success' => false, 'errors' => [['code' => 3036, 'message' => 'You have used up your daily free allocation of 10,000 neurons.']]], 429);

        $this->assertFallback($this->voice('Vous payez par Mobile Money.'));
        $this->assertTrue(NaturalVoice::dailyBlocked('cloudflare'));
        $this->assertSame('cloudflare_daily_quota', $this->lastFallbackCause());
        // Google n'est pas bloqué pour autant.
        $this->assertFalse(NaturalVoice::dailyBlocked('gemini'));

        // Plus aucun essai le même jour.
        $this->travel(3)->hours();
        $this->assertFallback($this->voice('Une autre phrase de la réponse.'));
        Http::assertSentCount(1);

        // Nouvelle journée de Cloudflare (0 h 00 UTC = 0 h 00 à Lomé) : le site réessaie tout seul.
        $this->travelTo(now()->utc()->addDay()->setTime(0, 5));
        $this->assertFalse(NaturalVoice::dailyBlocked('cloudflare'));
        $this->fakeAudio();
        $this->voice('Une autre phrase de la réponse.')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_a_busy_cloudflare_does_not_block_the_whole_day(): void
    {
        $this->enable();
        $this->cloudflare = fn () => Http::response(['success' => false, 'errors' => [['code' => 3040, 'message' => 'Capacity temporarily exceeded, please try again.']]], 429);

        $this->assertFallback($this->voice('Une phrase de la réponse.'));

        $this->assertSame('cloudflare_quota', $this->lastFallbackCause());
        $this->assertFalse(NaturalVoice::dailyBlocked('cloudflare'));
    }

    public function test_the_daily_cap_of_minutes_protects_the_free_offer_until_the_next_day(): void
    {
        $this->enable(['daily_minutes' => 1]);
        $this->travelTo(now()->utc()->setTime(10, 0));
        $this->fakeAudio(50.0);

        // 50 secondes, puis 50 de plus : le plafond d'une minute est dépassé après la deuxième.
        $this->voice('Première réponse lue par Cloudflare.')->assertOk();
        $this->voice('Deuxième réponse lue par Cloudflare.')->assertOk();
        $this->assertEqualsWithDelta(100 / 60, NaturalVoice::usedMinutes(), 0.02);
        $this->assertTrue(NaturalVoice::capReached());
        Http::assertSentCount(2);

        // Au-delà : voix du téléphone, sans aucune demande à Cloudflare.
        $this->assertFallback($this->voice('Troisième réponse.'));
        Http::assertSentCount(2);
        $this->assertSame('cloudflare_cap', $this->lastFallbackCause());

        // L'admin voit les minutes du jour et l'alerte.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('data-test="cloudflare-cap-reached"', false)
            ->assertSee('Plafond du jour atteint')
            ->assertSeeInOrder(['data-test="cloudflare-minutes-used"', '1,7'], false);

        // Lendemain (minuit à Lomé = minuit UTC) : la voix de Cloudflare revient toute seule.
        $this->travelTo(now()->utc()->addDay()->setTime(0, 1));
        $this->assertFalse(NaturalVoice::capReached());
        $this->fakeAudio(2.0);
        $this->voice('Nouvelle journée.')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_the_cap_counts_the_coming_answer_so_it_is_never_crossed_by_much(): void
    {
        $this->enable(['daily_minutes' => 1]);
        AssistantVoiceQuota::add('cloudflare', 58000);

        // 58 s déjà utilisées : une réponse estimée à plus de 2 s n'est pas demandée.
        $this->assertFallback($this->voice(str_repeat('Une longue réponse à lire. ', 4)));
        Http::assertNothingSent();
        $this->assertSame('cloudflare_cap', $this->lastFallbackCause());
    }

    public function test_sentences_kept_in_memory_are_still_spoken_after_the_cap_and_never_counted(): void
    {
        $this->enable(['daily_minutes' => 1]);
        $this->fakeAudio(4.0);

        $greeting = NaturalVoice::CALL_GOODBYE;
        $this->voice($greeting)->assertOk();
        $this->assertEqualsWithDelta(4000, AssistantVoiceQuota::usedMs('cloudflare'), 50);

        AssistantVoiceQuota::add('cloudflare', 60000);
        $this->assertTrue(NaturalVoice::capReached());

        // Phrase écrite à l'avance déjà en mémoire : servie sans demande, jamais comptée.
        $this->voice($greeting)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        Http::assertSentCount(1);
        $this->assertEqualsWithDelta(64000, AssistantVoiceQuota::usedMs('cloudflare'), 50);
    }

    public function test_without_the_keys_ama_speaks_with_the_phone_voice(): void
    {
        config(['services.cloudflare.api_token' => '']);
        $this->enable();

        // Voix naturelle éteinte pour le navigateur : aucune demande, voix du téléphone.
        $this->assertFalse(app(NaturalVoice::class)->enabled());
        $this->get(route('home'))->assertOk()->assertDontSee('fallbackEndpoint', false);
        $this->assertFallback($this->voice('Une phrase de la réponse.'));
        Http::assertNothingSent();

        // Le bouton « Écouter » explique ce qui manque.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['provider' => 'cloudflare'])
            ->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'CLOUDFLARE_API_TOKEN'));

        // L'admin affiche seulement « absente », jamais de valeur.
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('Clé Cloudflare absente')
            ->assertDontSee('Clé Cloudflare présente sur le serveur')
            ->assertDontSee(self::ACCOUNT);

        // Le service seul : cause « clé absente » si on l'appelle quand même.
        $voice = app(NaturalVoice::class);
        $this->assertNull($voice->speak('Une phrase.'));
        $this->assertSame('cloudflare_no_key', $voice->lastReason);
        $this->assertSame('cloudflare_no_key', $this->lastFallbackCause());
    }

    public function test_cloudflare_has_its_own_limit_per_minute(): void
    {
        // Limite de Google à 1 : sans effet sur Cloudflare ; limite de Cloudflare à 2.
        $this->enable(['minute_limit' => 1, 'cloudflare_minute_limit' => 2]);
        $this->fakeAudio(1.0);

        $this->voice('Première phrase.')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->voice('Deuxième phrase.')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertFallback($this->voice('Troisième phrase.'));
        $this->assertSame('site_minute_limit', $this->lastFallbackCause());
        Http::assertSentCount(2);

        $this->assertSame(2, NaturalVoice::minuteLimit('cloudflare'));
        $this->assertSame(1, NaturalVoice::minuteLimit('gemini'));
        // Valeur de départ raisonnable quand rien n'est réglé.
        $this->enable();
        $this->assertSame(20, NaturalVoice::minuteLimit('cloudflare'));
    }

    public function test_the_memory_is_separate_for_each_provider(): void
    {
        $this->enable(['provider' => 'gemini']);
        $this->voice(NaturalVoice::CALL_GOODBYE)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->assertTrue(app(NaturalVoice::class)->isCached(NaturalVoice::CALL_GOODBYE, provider: 'gemini'));
        $this->assertFalse(app(NaturalVoice::class)->isCached(NaturalVoice::CALL_GOODBYE, provider: 'cloudflare'));

        // Cloudflare choisi : la phrase gardée par Google n'est pas reprise, Cloudflare fabrique la sienne.
        $this->enable();
        $this->fakeAudio(3.0);
        $this->voice(NaturalVoice::CALL_GOODBYE)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->voice(NaturalVoice::CALL_GOODBYE)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        Http::assertSentCount(2);
        $this->assertTrue(app(NaturalVoice::class)->isCached(NaturalVoice::CALL_GOODBYE, provider: 'cloudflare'));
        $this->assertNotEmpty(Storage::disk('ama_voice')->allFiles('cloudflare'));
    }

    public function test_the_first_name_and_personal_data_are_never_sent_to_cloudflare(): void
    {
        $this->enable();
        $this->fakeAudio();
        $user = User::factory()->create(['name' => 'Kossi Mensah']);

        $this->voice('Merci Kossi, voici la réponse.', $user)->assertOk();
        Http::assertSent(fn (Request $request): bool => $request['prompt'] === 'Merci, voici la réponse.');

        // Numéro, e-mail ou lien : voix du téléphone, rien n'est envoyé.
        foreach (['Écrivez au +228 90 00 00 00.', 'Écrivez à contact@example.com.', 'Voir https://example.com/page.'] as $text) {
            $this->assertFallback($this->voice($text));
        }

        Http::assertSentCount(1);
        $this->assertSame('private', $this->lastFallbackCause());
    }

    public function test_the_admin_chooses_the_provider_listens_and_sees_only_the_key_status(): void
    {
        $admin = User::factory()->admin()->create();
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('Fournisseur de voix')
            ->assertSee('Cloudflare (MeloTTS)')
            ->assertSee('Google (Gemini)')
            ->assertSee('Clé Cloudflare présente sur le serveur')
            ->assertSee('Plafond de minutes d\'audio par jour (Cloudflare)')
            // Voix et modèles de Gemini : affichés seulement quand Google est choisi.
            ->assertSee('data-test="natural-voice-name" x-show="provider === \'gemini\'"', false)
            ->assertSee('data-test="natural-voice-model" x-show="provider === \'gemini\'"', false)
            ->assertDontSee(self::TOKEN)
            ->assertDontSee(self::ACCOUNT);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'provider' => 'gemini',
            'natural_voice' => '1',
            'natural_voice_provider' => 'cloudflare',
            'natural_voice_cloudflare_minute_limit' => '15',
            'natural_voice_daily_minutes' => '300',
        ])->assertRedirect();

        $saved = ((array) SiteSettings::get('site.voice_assistant'))['natural_voice'];
        $this->assertSame('cloudflare', $saved['provider']);
        $this->assertSame(15, $saved['cloudflare_minute_limit']);
        $this->assertSame(300, $saved['daily_minutes']);
        $this->assertSame('cloudflare', NaturalVoice::provider());
        $this->assertStringContainsString('voix naturelle : oui (Cloudflare MeloTTS, 15 par minute, plafond de 300 minutes par jour', (string) AdminActivity::query()->latest('id')->value('description'));

        // Plafond au-delà de l'offre gratuite : refusé.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'gemini', 'natural_voice_daily_minutes' => '600'])
            ->assertSessionHasErrors('natural_voice_daily_minutes');

        // « Écouter » avec Cloudflare : une seule demande, puis la mémoire.
        $this->fakeAudio(4.0);
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['provider' => 'cloudflare'])
            ->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['provider' => 'cloudflare'])->assertOk();
        // (L'enregistrement a aussi lancé la préparation des phrases écrites à l'avance : seules les demandes de la phrase
        // d'exemple sont comptées ici.)
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request['prompt'] === NaturalVoice::SAMPLE && $request['lang'] === 'fr'));
        $this->assertSame(1, AssistantVoiceEvent::query()->where('kind', AssistantVoiceEvent::GOOGLE)->where('source', AssistantVoiceEvent::SOURCE_PREVIEW)->where('provider', 'cloudflare')->count());

        // Offre du jour épuisée : message clair.
        Storage::fake('ama_voice');
        $this->cloudflare = fn () => Http::response(['success' => false, 'errors' => [['code' => 3036, 'message' => 'daily free allocation']]], 429);
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['provider' => 'cloudflare'])
            ->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'offre gratuite de Cloudflare est épuisée'));
    }

    public function test_the_duration_of_an_audio_file_is_read_from_the_file(): void
    {
        // MP3 : somme des trames, balise ID3 au début ignorée.
        $this->assertEqualsWithDelta(10000, AudioDuration::milliseconds(new SpeechAudio(self::mp3(10.0), 'audio/mpeg')), 30);
        $id3 = 'ID3'."\x04\x00\x00".chr(0).chr(0).chr(0).chr(20).str_repeat("\x00", 20);
        $this->assertEqualsWithDelta(5000, AudioDuration::milliseconds(new SpeechAudio($id3.self::mp3(5.0), 'audio/mpeg')), 30);

        // WAV : taille des données / octets par seconde (24 000 Hz, 16 bits, mono : 48 000 octets par seconde).
        $wav = 'RIFF'.pack('V', 36 + 96000).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16).'data'.pack('V', 96000).str_repeat("\x00", 96000);
        $this->assertSame(2000, AudioDuration::milliseconds(new SpeechAudio($wav)));

        // Fichier illisible : estimation d'après le texte (au moins une seconde).
        $this->assertSame(2000, AudioDuration::milliseconds(new SpeechAudio('pas un son', 'audio/mpeg'), str_repeat('a', 28)));
        $this->assertSame(1000, AudioDuration::estimate('Oui.'));
    }

    public function test_neurons_and_default_cap_stay_under_the_free_offer(): void
    {
        $this->assertSame(400, NaturalVoice::dailyMinutes());
        $this->assertLessThan(NaturalVoice::CLOUDFLARE_FREE_NEURONS, NaturalVoice::neurons(NaturalVoice::DAILY_MINUTES[2]));
        $this->assertSame(7452, NaturalVoice::neurons(400));
    }
}
