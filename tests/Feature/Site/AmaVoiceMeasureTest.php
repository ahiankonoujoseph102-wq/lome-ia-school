<?php

namespace Tests\Feature\Site;

use App\Assistant\NaturalVoice;
use App\Assistant\SpeechFailure;
use App\Contracts\SpeechSynthesizer;
use App\Jobs\PrepareAmaVoicePhrases;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantVoiceEvent;
use App\Models\AssistantVoiceUsage;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Fakes\FakeSpeechSynthesizer;
use Tests\TestCase;

/**
 * Voix naturelle d'Ama : mesure (temps mis par Google pour chaque demande, cause exacte de chaque passage à la voix du
 * téléphone, affichées dans l'admin) et phrases écrites à l'avance préparées en mémoire (accueil en « Bonjour » et
 * « Bonsoir », exactement dans les morceaux que demande le navigateur), sans jamais prendre la dernière demande de la
 * minute à un visiteur. Faux Google (synthèse simulée) : aucun vrai appel.
 */
class AmaVoiceMeasureTest extends TestCase
{
    use RefreshDatabase;

    private const string VISITOR = 'wwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwww';

    private FakeSpeechSynthesizer $speech;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ama_voice');
        $this->speech = new FakeSpeechSynthesizer;
        $this->app->instance(SpeechSynthesizer::class, $this->speech);
        config(['services.gemini.key' => 'cle-de-test-mesure']);
    }

    /**
     * @param  array<string, mixed>  $natural
     */
    private function enable(array $natural = []): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'natural_voice' => ['enabled' => true, 'voice' => 'Kore', 'model' => 'gemini-3.8-flash-lite-tts', 'minute_limit' => 3, ...$natural],
        ]]);
    }

    private function voice(string $text, ?User $user = null): TestResponse
    {
        $request = $user !== null ? $this->actingAs($user) : $this;

        return $request->withCredentials()->withCookie('ama_visitor', self::VISITOR)->postJson(route('assistant.voice'), ['text' => $text]);
    }

    public function test_the_time_taken_by_google_is_recorded_for_each_request_and_shown_in_the_admin(): void
    {
        $this->enable(['minute_limit' => 60]);

        $this->speech->delayMs = 30;
        $this->voice('La formation coûte vingt-cinq mille francs.')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->speech->delayMs = 90;
        $this->voice('Vous payez par Mobile Money depuis votre téléphone.')->assertOk()->assertHeader('Content-Type', 'audio/wav');

        $events = AssistantVoiceEvent::query()->where('kind', AssistantVoiceEvent::GOOGLE)->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertGreaterThanOrEqual(30, $events[0]->duration_ms);
        $this->assertGreaterThanOrEqual(90, $events[1]->duration_ms);
        $this->assertSame(AssistantVoiceEvent::SOURCE_SITE, $events[0]->source);
        $this->assertNull($events[0]->cause);
        $this->assertSame(mb_strlen('La formation coûte vingt-cinq mille francs.'), $events[0]->chars);
        // Aucun texte enregistré.
        $this->assertStringNotContainsString('formation', (string) json_encode(AssistantVoiceEvent::query()->get()->toArray()));

        $stats = AssistantVoiceEvent::todayStats();
        $this->assertSame(2, $stats['google_count']);
        $this->assertSame($events[1]->duration_ms, $stats['google_max_ms']);
        $this->assertSame((int) round(($events[0]->duration_ms + $events[1]->duration_ms) / 2), $stats['google_avg_ms']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('Temps de Google en moyenne')
            ->assertSee('Temps de Google au maximum')
            ->assertSeeInOrder(['data-test="natural-voice-google-max"', str_replace('.', ',', (string) round($stats['google_max_ms'] / 1000, 1)).' s'], false)
            ->assertSee('Aucun aujourd\'hui.', false);
    }

    public function test_every_fallback_to_the_phone_voice_is_recorded_with_its_exact_cause(): void
    {
        $this->enable(['minute_limit' => 2]);

        // Donnée personnelle (voulu) : lue par le téléphone.
        $this->voice('Écrivez-nous au 90 12 34 56.')->assertExactJson(['fallback' => true]);
        // Limite de Google (429), Google trop lent, erreur de Google.
        $this->speech->failure = new SpeechFailure('429', quota: true);
        $this->voice('Première réponse de test.')->assertExactJson(['fallback' => true]);
        $this->speech->failure = new SpeechFailure('délai', timeout: true);
        $this->voice('Deuxième réponse de test.')->assertExactJson(['fallback' => true]);
        // Limite par minute du site (2) atteinte.
        $this->speech->failure = null;
        $this->voice('Troisième réponse de test.')->assertExactJson(['fallback' => true]);
        $this->travel(2)->minutes();
        $this->speech->failure = new SpeechFailure('500');
        $this->voice('Quatrième réponse de test.')->assertExactJson(['fallback' => true]);

        // Passages décidés par le navigateur : délai dépassé (avec l'attente), lecture refusée, son coupé par le téléphone,
        // réseau, page ouverte avant la mise à jour (sans cause) ; une cause inventée n'est pas comptée.
        $this->postJson(route('assistant.voice.fallback'), ['cause' => 'timeout', 'waited' => 10000, 'outcome' => 'phone'])->assertOk();
        $this->postJson(route('assistant.voice.fallback'), ['cause' => 'timeout', 'waited' => 10001, 'outcome' => 'phone'])->assertOk();
        $this->postJson(route('assistant.voice.fallback'), ['cause' => 'playback_refused', 'waited' => 300, 'outcome' => 'phone'])->assertOk();
        $this->postJson(route('assistant.voice.fallback'), ['cause' => 'playback_paused', 'waited' => 2100, 'outcome' => 'phone'])->assertOk();
        $this->postJson(route('assistant.voice.fallback'), ['cause' => 'network', 'outcome' => 'phone'])->assertOk();
        $this->postJson(route('assistant.voice.fallback'), [])->assertOk();
        $this->postJson(route('assistant.voice.fallback'), ['cause' => '<script>'])->assertOk();

        $this->assertSame([
            'google_error' => 1, 'google_quota' => 1, 'google_timeout' => 1, 'network' => 1, 'old_page' => 1,
            'playback_paused' => 1, 'playback_refused' => 1, 'private' => 1, 'site_minute_limit' => 1, 'timeout' => 2,
        ], collect(AssistantVoiceEvent::todayStats()['causes'])->sortKeys()->all());
        // Aucune cause « Autre » : chaque passage enregistré a une cause précise, avec son libellé dans l'admin.
        $this->assertArrayNotHasKey('other', NaturalVoice::CAUSES);
        foreach (AssistantVoiceEvent::query()->pluck('cause')->filter() as $cause) {
            $this->assertArrayHasKey($cause, NaturalVoice::CAUSES);
        }
        $this->assertSame([10000, 10001], AssistantVoiceEvent::query()->where('cause', 'timeout')->orderBy('id')->pluck('duration_ms')->all());
        // Les anciens compteurs restent justes : 11 passages au total.
        $this->assertSame(11, AssistantVoiceUsage::today()['fallbacks']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('Passages à la voix du téléphone, par cause')
            ->assertSeeInOrder(['Voix pas prête avant le délai du téléphone', '2'])
            ->assertSee('Limite de demandes par minute du site')
            ->assertSee('Limite de Google atteinte (refus 429)')
            ->assertSee('Google trop lent (délai du serveur dépassé)')
            ->assertSee('Lecture refusée par le navigateur du téléphone')
            ->assertSee('Son mis en pause par le téléphone (micro, appel, notification, écouteurs) et pas repris')
            ->assertSee('Page ouverte avant la mise à jour du site (cause non transmise)')
            ->assertDontSee('>Autre<', false)
            ->assertSee('Réseau ou serveur du site injoignable')
            ->assertSee('Numéro, e-mail, lien ou prénom dans la phrase (voulu)');
    }

    public function test_the_listen_button_is_counted_apart_from_the_visitors(): void
    {
        $this->enable();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['voice' => 'Aoede', 'model' => 'gemini-3.8-flash-tts'])
            ->assertOk()->assertHeader('Content-Type', 'audio/wav');

        $this->assertSame([AssistantVoiceEvent::SOURCE_PREVIEW => 1], AssistantVoiceEvent::todayStats()['by_source']);
        $this->assertSame(0, AssistantVoiceUsage::today()['spoken']);
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSeeInOrder(['dont bouton « Écouter » et préparation', '1']);
    }

    public function test_the_prepared_texts_are_exactly_what_the_browser_asks_for_with_bonjour_and_bonsoir(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour reproduire les textes du navigateur.');
        }

        $this->enable();
        $program = 'const { personalGreeting, createSentenceSplitter, speakable } = await import('.json_encode('file://'.resource_path('js/ama.js')).');'
            .'const { CALL_GREETING, CALL_GOODBYE, CALL_ERROR, MISHEARD } = await import('.json_encode('file://'.resource_path('js/ama-call.js')).');'
            .'const { hidePrivateWords } = await import('.json_encode('file://'.resource_path('js/ama-voice.js')).');'
            // Comme le mode appel : phrases découpées, puis une seule demande si le tout tient dans la longueur réglée.
            .'const sent = (text, name) => { const s = createSentenceSplitter(); const parts = [...s.push(text), ...s.flush()].map(speakable);'
            .' const whole = parts.join(" "); return hidePrivateWords(whole.length <= '.NaturalVoice::splitLength().' ? whole : parts[0], [name]); };'
            .'console.log(JSON.stringify({'
            .' morning: sent(personalGreeting(CALL_GREETING, { hour: 9, firstName: "Kossi" }), "Kossi"),'
            .' evening: sent(personalGreeting(CALL_GREETING, { hour: 20, firstName: "Kossi" }), "Kossi"),'
            .' anonymous: sent(personalGreeting(CALL_GREETING, { hour: 20 }), ""),'
            .' goodbye: sent(CALL_GOODBYE, ""), error: sent(CALL_ERROR, ""), misheard: sent(MISHEARD, ""),'
            .' same: CALL_GREETING === '.json_encode(NaturalVoice::CALL_GREETING).' && CALL_ERROR === '.json_encode(NaturalVoice::CALL_ERROR).' }));';
        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $browser = json_decode(trim($process->getOutput()), true);

        $prepared = NaturalVoice::preparedTexts();

        $this->assertTrue($browser['same']);
        $this->assertSame('Bonjour, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.', $browser['morning']);
        $this->assertSame('Bonsoir, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.', $browser['evening']);
        $this->assertSame($browser['evening'], $browser['anonymous']);

        foreach (['morning', 'evening', 'goodbye', 'error', 'misheard'] as $key) {
            $this->assertContains($browser[$key], $prepared, $key);
        }

        // Le prénom n'est jamais préparé ni envoyé.
        $this->assertStringNotContainsString('Kossi', implode(' ', $prepared));
        $this->assertLessThanOrEqual(NaturalVoice::PREPARE_MAX, count($prepared));

        // Longueur de découpe plus courte qu'une phrase écrite à l'avance : première phrase seule, puis la suite (comme le
        // navigateur).
        $this->enable(['split_length' => 80]);
        $this->assertSame(['Je ne vous entends plus.', 'Merci pour votre appel, et à bientôt sur Lomé IA School !'], NaturalVoice::pieces(NaturalVoice::CALL_GOODBYE));
        $this->assertContains('Merci pour votre appel, et à bientôt sur Lomé IA School !', NaturalVoice::preparedTexts());
    }

    public function test_the_prepared_phrases_are_made_in_the_background_then_never_ask_google_again(): void
    {
        $this->enable(['minute_limit' => 3]);
        $total = count(NaturalVoice::preparedTexts());

        // Une minute : au plus 2 phrases (la troisième demande reste pour un visiteur), puis reprise une minute plus tard.
        Queue::fake();
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertSame(2, $this->speech->calls);
        $this->assertSame(1, RateLimiter::remaining('ama-voice:site-minute', 3));
        Queue::assertPushed(PrepareAmaVoicePhrases::class);

        // Les minutes suivantes : tout est préparé.
        for ($minute = 0; $minute < $total && app(NaturalVoice::class)->missingPreparedTexts() !== []; $minute++) {
            $this->travel(61)->seconds();
            (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        }

        $this->assertSame([], app(NaturalVoice::class)->missingPreparedTexts());
        $this->assertSame($total, $this->speech->calls);
        $this->assertSame($total, AssistantVoiceEvent::todayStats()['by_source'][AssistantVoiceEvent::SOURCE_PREPARE]);

        // Les visiteurs entendent l'accueil (« Bonjour » et « Bonsoir », prénom retiré) sans aucune demande à Google.
        $this->travel(61)->seconds();
        $user = User::factory()->create(['name' => 'Kossi Mensah']);
        $this->voice('Bonsoir Kossi, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.', $user)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->voice('Bonjour, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->voice('Je ne vous entends plus. Merci pour votre appel, et à bientôt sur Lomé IA School !')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->assertSame($total, $this->speech->calls);
        $this->assertSame(3, AssistantVoiceUsage::today()['cached']);

        // Tout est prêt : la tâche ne fait plus rien et ne se relance pas.
        Queue::fake();
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertSame($total, $this->speech->calls);
        Queue::assertNothingPushed();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('Phrases écrites à l\'avance prêtes en mémoire : '.$total.' sur '.$total, false);
    }

    public function test_the_preparation_never_takes_the_last_request_of_the_minute_and_stops_on_a_refusal(): void
    {
        $this->enable(['minute_limit' => 3]);

        // Un visiteur vient d'utiliser deux demandes : il n'en reste qu'une, laissée aux visiteurs.
        $this->voice('Première réponse de test.')->assertOk();
        $this->voice('Deuxième réponse de test.')->assertOk();
        // (Sans la pause de deux minutes après un appel, testée dans AmaVoicePhoneFallbackTest.)
        Cache::forget('ama-voice:call-activity');
        Queue::fake();
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertSame(2, $this->speech->calls);
        Queue::assertPushed(PrepareAmaVoicePhrases::class);
        $this->voice('Troisième réponse de test.')->assertOk()->assertHeader('Content-Type', 'audio/wav');

        // Refus de Google (quota du jour) : la préparation s'arrête sans se relancer (deux minutes après l'appel).
        $this->travel(NaturalVoice::CALL_QUIET_SECONDS + 1)->seconds();
        $this->speech->failure = new SpeechFailure('quota', quota: true, daily: true);
        Queue::fake();
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        Queue::assertNothingPushed();
        $this->assertTrue(NaturalVoice::dailyBlocked());

        // Voix éteinte : rien.
        $this->enable(['enabled' => false]);
        $calls = $this->speech->calls;
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertSame($calls, $this->speech->calls);
    }

    public function test_saving_the_settings_starts_the_preparation_and_it_runs_every_hour(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1', 'provider' => 'gemini', 'natural_voice' => '1', 'natural_voice_name' => 'Kore',
        ])->assertRedirect();
        Queue::assertPushed(PrepareAmaVoicePhrases::class, 1);

        // Voix éteinte : aucune préparation.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'provider' => 'gemini', 'natural_voice' => '0'])->assertRedirect();
        Queue::assertPushed(PrepareAmaVoicePhrases::class, 1);

        $events = collect(app(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->description, 'PrepareAmaVoicePhrases'));
        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }

    public function test_a_memory_that_cannot_be_written_is_reported_in_the_admin(): void
    {
        $this->enable();
        config(['filesystems.disks.ama_voice' => ['driver' => 'local', 'root' => '/proc/ama-voice-impossible', 'throw' => false]]);
        Storage::forgetDisk('ama_voice');

        $this->voice('Bonsoir, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.')->assertOk()->assertHeader('Content-Type', 'audio/wav');

        $this->assertSame(1, AssistantVoiceEvent::todayStats()['memory_errors']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('data-test="natural-voice-memory-error"', false);
    }

    public function test_a_visitor_name_in_the_conversation_is_still_never_sent(): void
    {
        $this->enable();
        $conversation = AssistantConversation::query()->create([
            'public_id' => 'conv-mesure',
            'visitor_hash' => hash_hmac('sha256', self::VISITOR, (string) config('app.key')),
            'page' => 'accueil',
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create(['role' => AssistantMessage::VISITOR, 'content' => 'je suis afi']);

        $this->voice('Merci Afi, je transmets votre question.')->assertExactJson(['fallback' => true]);

        $this->assertSame(0, $this->speech->calls);
        $this->assertSame(['private' => 1], AssistantVoiceEvent::todayStats()['causes']);
    }

    public function test_the_events_table_migration_resumes_and_keeps_short_index_names(): void
    {
        $migration = require database_path('migrations/2026_10_17_100000_create_assistant_voice_events_table.php');
        $migration->up();
        $this->assertTrue(Schema::hasIndex('assistant_voice_events', ['day', 'kind']));

        // Échec au milieu : table créée, index absent.
        Schema::drop('assistant_voice_events');
        Schema::create('assistant_voice_events', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('kind', 20);
            $table->string('cause', 40)->nullable();
            $table->string('source', 20)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('chars')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('assistant_voice_events', ['day', 'kind']));
        foreach (Schema::getIndexes('assistant_voice_events') as $index) {
            $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
        }
    }
}
