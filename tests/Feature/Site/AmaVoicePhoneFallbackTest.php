<?php

namespace Tests\Feature\Site;

use App\Assistant\NaturalVoice;
use App\Assistant\SpeechFailure;
use App\Contracts\SpeechSynthesizer;
use App\Jobs\PrepareAmaVoicePhrases;
use App\Models\AdminActivity;
use App\Models\AssistantVoiceEvent;
use App\Models\AssistantVoiceUsage;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeSpeechSynthesizer;
use Tests\TestCase;

/**
 * Voix naturelle d'Ama côté serveur : interrupteur « Voix du téléphone en secours » (allumé par défaut ; éteint, une
 * réponse que Gemini ne dit pas est seulement affichée, et comptée comme telle), refus d'une suite de réponse déjà
 * commencée par Gemini (« voiced » : affichée sans voix), et préparation des phrases écrites à l'avance mise en pause
 * pendant les appels. Faux Google : aucun vrai appel.
 */
class AmaVoicePhoneFallbackTest extends TestCase
{
    use RefreshDatabase;

    private const string VISITOR = 'vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv';

    private FakeSpeechSynthesizer $speech;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ama_voice');
        $this->speech = new FakeSpeechSynthesizer;
        $this->app->instance(SpeechSynthesizer::class, $this->speech);
        config(['services.gemini.key' => 'cle-de-test-secours']);
        // La préparation des phrases est lancée par l'enregistrement des réglages : simulée ici (testée plus bas).
        Queue::fake();
    }

    /**
     * @param  array<string, mixed>  $natural
     */
    private function enable(array $natural = []): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'natural_voice' => ['enabled' => true, 'voice' => 'Kore', 'model' => 'gemini-3.8-flash-lite-tts', 'minute_limit' => 60, ...$natural],
        ]]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function voice(string $text, array $extra = []): TestResponse
    {
        return $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)->postJson(route('assistant.voice'), ['text' => $text, ...$extra]);
    }

    public function test_the_switch_is_on_by_default_saved_from_the_admin_and_sent_to_the_page(): void
    {
        $this->enable();
        $this->assertTrue(NaturalVoice::phoneFallback());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('data-test="natural-voice-phone-fallback"', false)
            ->assertSee('Voix du téléphone en secours')
            ->assertSee('Parties de réponse affichées sans voix');

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'provider' => 'gemini',
            'natural_voice' => '1',
            'natural_voice_phone_fallback' => '0',
        ])->assertRedirect();

        $this->assertFalse(NaturalVoice::phoneFallback());
        $this->assertFalse(((array) SiteSettings::get('site.voice_assistant'))['natural_voice']['phone_fallback']);
        $this->assertStringContainsString('sans voix du téléphone en secours, réponse affichée après 10 s', (string) AdminActivity::query()->latest('id')->value('description'));

        // La page reçoit le réglage (jamais la clé).
        $this->get(route('home'))->assertOk()->assertSee('phoneFallback\\u0022:false', false)->assertDontSee('cle-de-test-secours');

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'provider' => 'gemini',
            'natural_voice' => '1',
            'natural_voice_phone_fallback' => '1',
        ])->assertRedirect();
        $this->assertTrue(NaturalVoice::phoneFallback());
    }

    public function test_without_the_phone_voice_a_failure_is_counted_as_shown_without_voice(): void
    {
        $this->enable(['phone_fallback' => false]);

        $this->speech->failure = new SpeechFailure('429', quota: true);
        $this->voice('Première réponse de test.')->assertExactJson(['fallback' => true]);
        $this->postJson(route('assistant.voice.fallback'), ['cause' => 'timeout', 'waited' => 10000, 'outcome' => 'silent'])->assertOk();

        $stats = AssistantVoiceEvent::todayStats();
        $this->assertSame([], $stats['causes']);
        $this->assertSame(['google_quota' => 1, 'timeout' => 1], collect($stats['silent_causes'])->sortKeys()->all());
        $this->assertSame(2, $stats['silent']);
        // Aucun passage à la voix du téléphone n'est compté.
        $this->assertSame(0, AssistantVoiceUsage::today()['fallbacks']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('data-test="natural-voice-silent-causes"', false)
            ->assertSeeInOrder(['Parties de réponse affichées sans voix, par cause', 'Limite de Google atteinte (refus 429)']);
    }

    public function test_a_refused_continuation_after_gemini_was_heard_is_shown_without_voice(): void
    {
        $this->enable(['minute_limit' => 1]);

        $this->voice('Première phrase de la réponse.')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        // La suite, demandée pendant que Gemini parle, dépasse la limite par minute du site.
        $this->voice('La suite de la réponse.', ['voiced' => true])->assertExactJson(['fallback' => true]);
        // Une nouvelle réponse (rien d'entendu encore) refusée : voix du téléphone.
        $this->voice('Une autre réponse.')->assertExactJson(['fallback' => true]);

        $stats = AssistantVoiceEvent::todayStats();
        $this->assertSame(['site_minute_limit' => 1], $stats['silent_causes']);
        $this->assertSame(['site_minute_limit' => 1], $stats['causes']);
        $this->assertSame(1, AssistantVoiceUsage::today()['fallbacks']);
    }

    public function test_the_preparation_waits_two_minutes_after_any_call_activity(): void
    {
        $this->enable(['minute_limit' => 3]);

        // Un visiteur appelle Ama (accueil lu par la voix naturelle) : aucune préparation pendant deux minutes.
        $this->voice('Bonjour, je suis Ama.')->assertOk();
        $calls = $this->speech->calls;
        $this->assertTrue(NaturalVoice::callRecentlyActive());

        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertSame($calls, $this->speech->calls);
        Queue::assertPushed(PrepareAmaVoicePhrases::class);

        $this->travel(NaturalVoice::CALL_QUIET_SECONDS - 10)->seconds();
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertSame($calls, $this->speech->calls);

        // Un message du mode appel compte aussi comme activité.
        $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)->post(route('assistant.call'), ['message' => ''])->assertOk();
        $this->travel(NaturalVoice::CALL_QUIET_SECONDS - 10)->seconds();
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertSame($calls, $this->speech->calls);

        // Deux minutes de calme : la préparation reprend.
        $this->travel(11)->seconds();
        $this->assertFalse(NaturalVoice::callRecentlyActive());
        (new PrepareAmaVoicePhrases)->handle(app(NaturalVoice::class));
        $this->assertGreaterThan($calls, $this->speech->calls);
    }
}
