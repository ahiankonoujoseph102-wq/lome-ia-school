<?php

namespace Tests\Feature\Site;

use App\Admin\AdminMenu;
use App\Assistant\AmaPrompt;
use App\Enums\AdminAction;
use App\Enums\AssistantIncidentType;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\AssistantConversation;
use App\Models\AssistantIncident;
use App\Models\AssistantMessage;
use App\Models\AssistantUsage;
use App\Models\Formation;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\VoiceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Ama (Gemini) : fenêtre de discussion, appel de Gemini par le serveur uniquement, limites, relève WhatsApp,
 * incidents, page « Conversations d'Ama », effacement après 90 jours. Faux Gemini (Http::fake) : aucun vrai appel.
 */
class AmaGeminiTest extends TestCase
{
    use RefreshDatabase;

    private const string KEY = 'cle-de-test-GEMINI-9f8e7d6c5b4a';

    private const string VISITOR = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.gemini.key' => self::KEY, 'services.gemini.model' => 'modele-flash-de-test']);
        $this->settings();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values = []): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', ...$values]]);
    }

    private function fakeGemini(string $text = 'Bonjour, je suis Ama. Touchez « Acheter maintenant ».'): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(self::reply($text))]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function reply(string $text): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => 'STOP']]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ask(string $message, array $data = [], string $visitor = self::VISITOR): TestResponse
    {
        return $this->withCredentials()->withCookie('ama_visitor', $visitor)->postJson(route('assistant.message'), ['message' => $message, ...$data]);
    }

    /**
     * Corps JSON de la dernière demande envoyée à Gemini.
     *
     * @return array<string, mixed>
     */
    private function lastGeminiBody(): array
    {
        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded, 'Aucune demande envoyée à Gemini.');

        /** @var Request $request */
        $request = $recorded->last()[0];

        return (array) $request->data();
    }

    private function assertFallback(TestResponse $response, AssistantIncidentType $type): void
    {
        $response->assertOk()
            ->assertJson(['fallback' => true, 'reply' => $type->visitorMessage()])
            ->assertJsonPath('whatsapp', fn (string $url): bool => str_starts_with($url, 'https://wa.me/22872240231'));
        $this->assertTrue(AssistantIncident::query()->where('type', $type)->exists(), 'Incident non enregistré : '.$type->value);
        $this->assertStringNotContainsString(self::KEY, (string) $response->getContent());
    }

    public function test_ama_gemini_is_the_default_and_the_chat_window_is_on_the_page(): void
    {
        $this->assertSame(VoiceAssistant::GEMINI, VoiceAssistant::provider());
        $this->assertTrue(VoiceAssistant::enabled());

        $html = (string) $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-test="ama-chat"', false)
            ->assertSee('Parler avec notre assistante')
            ->assertSee('Lire les réponses à voix haute')
            ->assertSee('Écrivez-nous sur WhatsApp')
            ->assertSee('Ne partagez jamais de code secret ni de mot de passe.')
            ->getContent();

        // Aucun script d'ElevenLabs, aucune adresse de Google, jamais la clé.
        foreach (['unpkg.com', 'elevenlabs', 'generativelanguage', self::KEY, 'agent_9901'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
        // Adresse du serveur du site (dans la configuration du composant, où « / » est échappé).
        $this->assertMatchesRegularExpression('#localhost:8000\\\\*/assistante\\\\*/message#', $html);
    }

    public function test_the_button_stays_hidden_without_the_key(): void
    {
        config(['services.gemini.key' => null]);

        $this->assertFalse(VoiceAssistant::enabled());
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="voice-assistant"', false);

        $this->ask('Bonjour')->assertOk()->assertJson(['fallback' => true]);
        Http::assertNothingSent();
    }

    public function test_a_normal_reply_uses_the_server_key_the_model_the_guide_the_prices_and_the_page_context(): void
    {
        $this->fakeGemini();
        $formation = Formation::factory()->create(['title' => 'Formation Exemple ChatGPT', 'price' => 25000]);

        $response = $this->ask('Comment acheter ?', ['context' => [
            'page' => 'page de commande et de paiement',
            'etape' => 'étape 3 sur 4 : envoyer l\'argent',
            'formation' => $formation->title,
            'montant_a_payer' => '25 000 FCFA',
            'connecte' => true,
            'email_confirme' => true,
            'difficulte' => 'promo_refuse',
        ]])->assertOk()->assertJson(['fallback' => false, 'reply' => 'Bonjour, je suis Ama. Touchez « Acheter maintenant ».']);

        $this->assertTrue(str_contains((string) $response->json('conversation'), '-'));

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/modele-flash-de-test:generateContent'
                // Clé dans l'en-tête, jamais dans l'adresse.
                && $request->header('x-goog-api-key') === [self::KEY]
                && ! str_contains($request->url(), self::KEY);
        });

        $body = $this->lastGeminiBody();
        $instructions = (string) data_get($body, 'system_instruction.parts.0.text');
        $this->assertStringContainsString('Tu es Ama, l\'assistante IA de Lomé IA School', $instructions);
        $this->assertStringContainsString('Formation Exemple ChatGPT : 25'."\u{00A0}".'000'."\u{00A0}".'FCFA', $instructions);
        $this->assertStringContainsString('## 6. Payer par Mobile Money', $instructions);
        $this->assertStringContainsString('Étape : étape 3 sur 4 : envoyer l\'argent.', $instructions);
        $this->assertStringContainsString('Montant à payer : 25 000 FCFA.', $instructions);
        $this->assertStringContainsString('Difficulté repérée par le site : Le code promo saisi a été refusé.', $instructions);
        $this->assertSame([['role' => 'user', 'parts' => [['text' => 'Comment acheter ?']]]], $body['contents']);

        $conversation = AssistantConversation::query()->sole();
        $this->assertSame(2, $conversation->messages_count);
        $this->assertSame('page de commande et de paiement', $conversation->page);
        $this->assertSame(1, AssistantUsage::requestsToday());
    }

    public function test_a_price_changed_in_the_admin_is_known_by_ama_at_once(): void
    {
        $this->fakeGemini();
        $formation = Formation::factory()->create(['title' => 'Formation Exemple Prix', 'price' => 25000]);

        $this->ask('Quel est le prix ?')->assertOk();
        $this->assertStringContainsString('25'."\u{00A0}".'000', (string) data_get($this->lastGeminiBody(), 'system_instruction.parts.0.text'));

        $formation->update(['price' => 30000]);

        $this->ask('Et maintenant ?')->assertOk();
        $instructions = (string) data_get($this->lastGeminiBody(), 'system_instruction.parts.0.text');
        $this->assertStringContainsString('Formation Exemple Prix : 30'."\u{00A0}".'000'."\u{00A0}".'FCFA', $instructions);
        $this->assertStringNotContainsString('25'."\u{00A0}".'000', $instructions);

        // Formation masquée : Ama ne la propose plus.
        $formation->update(['is_active' => false]);
        $this->ask('Et encore ?')->assertOk();
        $this->assertStringNotContainsString('Formation Exemple Prix', (string) data_get($this->lastGeminiBody(), 'system_instruction.parts.0.text'));
    }

    public function test_the_conversation_history_is_sent_and_belongs_to_the_visitor_only(): void
    {
        $this->fakeGemini('Réponse d\'Ama.');

        $id = $this->ask('Première question')->json('conversation');
        $this->ask('Deuxième question', ['conversation' => $id])->assertJson(['conversation' => $id]);

        $this->assertSame([
            ['role' => 'user', 'parts' => [['text' => 'Première question']]],
            ['role' => 'model', 'parts' => [['text' => 'Réponse d\'Ama.']]],
            ['role' => 'user', 'parts' => [['text' => 'Deuxième question']]],
        ], $this->lastGeminiBody()['contents']);

        // Un autre navigateur ne peut pas reprendre cette conversation : il en commence une nouvelle.
        $other = $this->ask('Je reprends ?', ['conversation' => $id], str_repeat('b', 40))->json('conversation');
        $this->assertNotSame($id, $other);
        $this->assertCount(1, $this->lastGeminiBody()['contents']);
    }

    public function test_no_personal_data_is_sent_to_gemini_nor_stored(): void
    {
        $this->fakeGemini();

        $this->ask('Je suis afi.mensah@example.com, mon numéro +228 90 12 34 56, mon code secret 4827, mot de passe: Soleil2026! et mon code 583920. J\'ai payé 25000 FCFA en 2026.', ['context' => [
            'page' => 'page d\'inscription',
            'nom' => 'Afi Mensah',
            'email' => 'afi.mensah@example.com',
            'telephone' => '+22890123456',
            'erreur' => ['cle' => 'email', 'texte' => 'L\'adresse afi.mensah@example.com est déjà utilisée par le 90123456.'],
        ]])->assertOk();

        $sent = json_encode($this->lastGeminiBody(), JSON_UNESCAPED_UNICODE);
        $stored = AssistantMessage::query()->where('role', 'user')->sole()->content;

        foreach ([$sent, $stored] as $text) {
            foreach (['afi.mensah@example.com', 'Afi Mensah', '90 12 34 56', '90123456', '4827', '583920', 'Soleil2026'] as $personal) {
                $this->assertStringNotContainsString($personal, (string) $text);
            }
        }

        $this->assertStringContainsString('[adresse e-mail]', $stored);
        $this->assertStringContainsString('[numéro]', $stored);
        $this->assertStringContainsString('code secret [masqué]', $stored);
        $this->assertStringContainsString('[code]', $stored);
        // Les montants et les années restent lisibles.
        $this->assertStringContainsString('25000 FCFA en 2026', $stored);
        $this->assertStringNotContainsString(self::VISITOR, (string) AssistantConversation::query()->sole()->visitor_hash);
    }

    public function test_the_visitor_daily_limit_hands_over_to_whatsapp(): void
    {
        $this->fakeGemini();
        $this->settings(['visitor_daily_limit' => 2]);

        $this->ask('Un')->assertJson(['fallback' => false]);
        $this->ask('Deux')->assertJson(['fallback' => false]);
        $this->assertFallback($this->ask('Trois'), AssistantIncidentType::VisitorDailyLimit);
        Http::assertSentCount(2);

        // Un autre visiteur peut toujours écrire.
        $this->ask('Bonjour', [], str_repeat('c', 40))->assertJson(['fallback' => false]);
    }

    public function test_the_site_daily_limit_protects_the_free_quota(): void
    {
        $this->fakeGemini();
        $this->settings(['daily_limit' => 1]);

        $this->ask('Un')->assertJson(['fallback' => false]);
        $this->assertFallback($this->ask('Deux', [], str_repeat('d', 40)), AssistantIncidentType::GlobalDailyLimit);
        Http::assertSentCount(1);
        $this->assertSame(1, AssistantUsage::requestsToday());
    }

    public function test_too_many_messages_per_minute_are_refused(): void
    {
        $this->fakeGemini();
        $this->settings(['visitor_minute_limit' => 2]);

        $this->ask('Un')->assertJson(['fallback' => false]);
        $this->ask('Deux')->assertJson(['fallback' => false]);
        $this->assertFallback($this->ask('Trois'), AssistantIncidentType::VisitorMinuteLimit);
        Http::assertSentCount(2);
    }

    public function test_the_site_limit_per_minute_is_respected(): void
    {
        $this->fakeGemini();
        $this->settings(['minute_limit' => 1]);

        $this->ask('Un')->assertJson(['fallback' => false]);
        $this->assertFallback($this->ask('Deux', [], str_repeat('e', 40)), AssistantIncidentType::GlobalMinuteLimit);
        Http::assertSentCount(1);
    }

    public function test_a_too_long_message_gets_a_clear_notice_without_calling_gemini(): void
    {
        $this->fakeGemini();
        $this->settings(['max_length' => 60]);

        $this->ask(str_repeat('a', 61))
            ->assertOk()
            ->assertJson(['fallback' => false, 'reply' => '', 'notice' => 'Votre message est trop long : 60 caractères au maximum. Raccourcissez-le un peu, puis renvoyez-le.']);
        $this->ask('   ')->assertOk()->assertJsonPath('notice', 'Écrivez votre question, puis touchez « Envoyer ».');
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: int, 2: AssistantIncidentType}>
     */
    public static function geminiFailures(): array
    {
        return [
            'erreur 500' => [['error' => ['code' => 500, 'status' => 'INTERNAL', 'message' => 'Internal error']], 500, AssistantIncidentType::GeminiError],
            'erreur 400' => [['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Model not found']], 400, AssistantIncidentType::GeminiError],
            'quota 429' => [['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded']], 429, AssistantIncidentType::GeminiQuota],
            'réponse vide' => [['candidates' => [['finishReason' => 'SAFETY']]], 200, AssistantIncidentType::EmptyReply],
            'réponse bloquée' => [['promptFeedback' => ['blockReason' => 'OTHER']], 200, AssistantIncidentType::EmptyReply],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('geminiFailures')]
    public function test_a_gemini_failure_never_shows_a_technical_error(array $body, int $status, AssistantIncidentType $type): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body, $status)]);

        $this->assertFallback($this->ask('Bonjour'), $type);
        $this->assertSame(1, (int) AssistantUsage::query()->value('failures'));
        // Le message du visiteur est gardé (compté dans sa limite), sans réponse d'Ama.
        $this->assertSame(['user'], AssistantMessage::query()->pluck('role')->all());
        $this->assertTrue(AssistantConversation::query()->sole()->fallback_shown);
    }

    public function test_a_timeout_hands_over_to_whatsapp(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->assertFallback($this->ask('Bonjour'), AssistantIncidentType::GeminiTimeout);
    }

    public function test_a_passing_503_is_retried_once(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503)
            ->push(self::reply('Me revoici.'))]);

        $this->ask('Bonjour')->assertJson(['fallback' => false, 'reply' => 'Me revoici.']);
        Http::assertSentCount(2);
    }

    public function test_the_key_never_appears_in_pages_incidents_or_logs(): void
    {
        // Même si Google recopiait la clé dans son message d'erreur.
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key '.self::KEY.' not valid']], 400)]);
        $log = storage_path('logs/laravel.log');
        $before = is_file($log) ? (string) file_get_contents($log) : '';

        $this->assertFallback($this->ask('Bonjour'), AssistantIncidentType::GeminiError);
        $incident = AssistantIncident::query()->sole();
        $this->assertStringNotContainsString(self::KEY, (string) $incident->detail);
        $this->assertStringContainsString('[clé]', (string) $incident->detail);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        foreach ([route('admin.assistant.index'), route('admin.settings.edit', 'assistante'), route('admin.assistant.show', AssistantConversation::query()->sole())] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertDontSee(self::KEY);
        }
        auth()->logout();
        $this->get(route('home'))->assertDontSee(self::KEY);

        $after = is_file($log) ? (string) file_get_contents($log) : '';
        $this->assertStringNotContainsString(self::KEY, substr($after, strlen($before)));
        $this->assertStringNotContainsString(self::KEY, (string) file_get_contents(resource_path('js/site.js')));
    }

    public function test_the_admin_page_lists_conversations_requests_and_incidents(): void
    {
        $this->fakeGemini('Voici la réponse d\'Ama.');
        $id = $this->ask('Comment réserver la consultation ?', ['context' => ['page' => 'page Contact'], 'voice' => true])->json('conversation');
        $this->settings(['visitor_daily_limit' => 1]);
        $this->ask('Encore une question', ['conversation' => $id])->assertJson(['fallback' => true]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $conversation = AssistantConversation::query()->sole();
        $this->assertTrue($conversation->voice_used);

        $this->actingAs($admin)->get(route('admin.assistant.index'))
            ->assertOk()
            ->assertSee('Conversations d\'Ama')
            ->assertSeeInOrder(['Demandes envoyées à Google aujourd\'hui', '1', '/ 400'])
            ->assertSee(AssistantIncidentType::VisitorDailyLimit->label())
            ->assertSee('page Contact')
            ->assertSee('Micro utilisé')
            ->assertSee('Relève WhatsApp');

        $this->actingAs($admin)->get(route('admin.assistant.show', $conversation))
            ->assertOk()
            ->assertSeeInOrder(['Comment réserver la consultation ?', 'Voici la réponse d\'Ama.']);

        $this->actingAs($admin)->get(route('admin.index'))->assertOk();
        $this->assertStringContainsString('Conversations d', collect(AdminMenu::groups($admin))->flatMap(fn (array $group) => array_column($group['links'], 'label'))->implode('|'));
    }

    public function test_moderators_and_visitors_cannot_see_the_conversations(): void
    {
        $this->get(route('admin.assistant.index'))->assertRedirect(route('login'));

        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $this->actingAs($moderator)->get(route('admin.assistant.index'))->assertForbidden();

        $student = User::factory()->create();
        $this->actingAs($student)->get(route('admin.assistant.index'))->assertForbidden();
    }

    public function test_the_admin_chooses_the_assistant_its_instructions_and_its_limits(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))
            ->assertOk()
            ->assertSee('Ama (Gemini)')
            ->assertSee('ElevenLabs')
            ->assertSee('Clé Gemini présente sur le serveur.')
            ->assertSee('modele-flash-de-test')
            ->assertSee('Tu es Ama, l&#039;assistante IA de Lomé IA School', false)
            ->assertSee('name="visitor_daily_limit"', false)
            ->assertSee('value="30"', false)
            ->assertSee('value="500"', false)
            ->assertDontSee(self::KEY);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'provider' => 'gemini',
            'instructions' => 'Tu es Ama. Instructions modifiées pour le test.',
            'visitor_daily_limit' => '20',
            'max_length' => '300',
            'daily_limit' => '200',
            'visitor_minute_limit' => '3',
            'minute_limit' => '8',
            'agent_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Tu es Ama. Instructions modifiées pour le test.', AmaPrompt::instructions());
        $this->assertSame(20, VoiceAssistant::limit('visitor_daily_limit'));
        $this->assertSame(300, VoiceAssistant::limit('max_length'));
        $this->assertSame(200, VoiceAssistant::limit('daily_limit'));
        // L'identifiant de l'agent ElevenLabs est gardé (inutilisé avec Ama).
        $this->assertSame('agent_9901m3phrpdwedt88vsdqmnwk09z', SiteSettings::get('site.voice_assistant.agent_id'));
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'like', '%Ama (Gemini), 20 messages par visiteur%instructions modifiées%')->exists());

        $this->fakeGemini();
        $this->ask('Bonjour')->assertOk();
        $this->assertStringStartsWith('Tu es Ama. Instructions modifiées pour le test.', (string) data_get($this->lastGeminiBody(), 'system_instruction.parts.0.text'));

        // Instructions vidées : retour au texte d'origine. Limite hors bornes : refusée.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'provider' => 'gemini', 'instructions' => ''])->assertSessionHasNoErrors();
        $this->assertSame(AmaPrompt::DEFAULT_INSTRUCTIONS, AmaPrompt::instructions());
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'provider' => 'gemini', 'visitor_daily_limit' => '0'])
            ->assertSessionHasErrors(['visitor_daily_limit' => 'Le nombre doit être d\'au moins 1.']);
    }

    public function test_choosing_elevenlabs_brings_back_the_old_widget_without_the_chat_window(): void
    {
        $this->settings(['provider' => 'elevenlabs', 'agent_id' => 'agent_9901m3phrpdwedt88vsdqmnwk09z']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('agent_9901m3phrpdwedt88vsdqmnwk09z', false)
            ->assertDontSee('data-test="ama-chat"', false);

        $this->ask('Bonjour')->assertOk()->assertJson(['fallback' => true]);
        Http::assertNothingSent();
    }

    public function test_conversations_and_incidents_are_erased_after_90_days(): void
    {
        $old = AssistantConversation::query()->create(['public_id' => '11111111-1111-4111-8111-111111111111', 'visitor_hash' => 'x', 'last_message_at' => now()->subDays(91)]);
        $old->messages()->create(['role' => 'user', 'content' => 'Ancien message']);
        $recent = AssistantConversation::query()->create(['public_id' => '22222222-2222-4222-8222-222222222222', 'visitor_hash' => 'y', 'last_message_at' => now()->subDays(89)]);
        AssistantIncident::query()->create(['type' => AssistantIncidentType::GeminiError])->forceFill(['created_at' => now()->subDays(91)])->save();
        AssistantIncident::query()->create(['type' => AssistantIncidentType::GeminiError]);
        AssistantUsage::query()->create(['day' => now()->subDays(95)->format('Y-m-d'), 'requests' => 3]);

        $this->artisan('model:prune', ['--model' => [AssistantConversation::class, AssistantIncident::class, AssistantUsage::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertSame(0, AssistantMessage::query()->count());
        $this->assertSame(1, AssistantIncident::query()->count());
        $this->assertSame(0, AssistantUsage::query()->count());
    }

    public function test_the_prune_is_scheduled_every_night(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('model:prune')->assertSuccessful();
    }

    public function test_the_privacy_policy_explains_gemini(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Ama est une intelligence artificielle.')
            ->assertSee('Gemini, un service d\'intelligence artificielle de Google', false)
            ->assertSee('Sur cette offre, Google peut utiliser les échanges pour améliorer ses services.')
            ->assertSee('aucune donnée personnelle n\'est transmise', false)
            ->assertSee('conservées 90 jours')
            ->assertDontSee('traitées par le service ElevenLabs');
    }

    public function test_the_microphone_only_opens_when_its_button_is_touched(): void
    {
        $script = (string) file_get_contents(resource_path('js/site.js'));
        $component = substr($script, (int) strpos($script, "Alpine.data('voiceAssistant'"));
        $component = substr($component, 0, (int) strpos($component, 'const startAlpine'));
        $partial = (string) file_get_contents(resource_path('views/site/partials/voice-assistant.blade.php'));

        // Un seul endroit démarre la reconnaissance vocale : listen(), appelé seulement par le bouton du micro.
        $this->assertSame(1, substr_count($component, 'recognition.start()'));
        $this->assertSame(1, substr_count($partial, '@click="listen()"'));
        $this->assertStringNotContainsString('this.listen(', $component);
        $this->assertStringNotContainsString('getUserMedia', $component);
        // Gemini n'est jamais appelé par le navigateur.
        $this->assertStringNotContainsString('generativelanguage', $component);
        $this->assertStringNotContainsString('GEMINI', $component);
    }

    /**
     * Logique du navigateur (resources/js/ama.js) exécutée avec Node.js : voix disponibles ou non,
     * voix française, conversation gardée dans l'onglet.
     */
    public function test_browser_voice_and_conversation_logic(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama.js.');
        }

        $module = json_encode('file://'.resource_path('js/ama.js'));
        $program = <<<JS
            const { speechSupport, pickFrenchVoice, speakable, loadConversation, saveConversation } = await import({$module});
            const memory = () => { const data = {}; return { getItem: (k) => data[k] ?? null, setItem: (k, v) => { data[k] = String(v); } }; };
            const out = {};

            function Recognition() {}
            function Utterance() {}
            const full = speechSupport({ webkitSpeechRecognition: Recognition, speechSynthesis: { speak() {} }, SpeechSynthesisUtterance: Utterance, isSecureContext: true });
            out.full = { listen: full.listen, speak: full.speak };
            // Navigateur sans voix (certains iPhone, anciens navigateurs) : ni micro ni lecture, l'écrit reste.
            out.none = speechSupport({});
            out.none = { listen: out.none.listen, speak: out.none.speak };
            out.insecure = speechSupport({ SpeechRecognition: Recognition, isSecureContext: false }).listen;

            out.voice = pickFrenchVoice([{ lang: 'en-US', name: 'en' }, { lang: 'fr-CA', name: 'ca' }, { lang: 'fr-FR', name: 'fr', localService: false }])?.name;
            out.noVoice = pickFrenchVoice([{ lang: 'en-US' }]);
            out.speakable = speakable('• Touchez **« Payer »**\\n# Ensuite');

            const storage = memory();
            saveConversation(storage, 'abc', [{ from: 'visitor', text: 'Bonjour', extra: 'x' }, { from: 'ama', text: 'Salut', fallback: true }]);
            out.loaded = loadConversation(storage);
            out.broken = loadConversation({ getItem: () => '{pas du json' });
            out.denied = loadConversation({ getItem: () => { throw new Error('refusé'); } });
            saveConversation({ setItem: () => { throw new Error('refusé'); } }, 'x', []);

            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame(['listen' => true, 'speak' => true], $out['full']);
        $this->assertSame(['listen' => false, 'speak' => false], $out['none']);
        $this->assertFalse($out['insecure']);
        $this->assertSame('fr', $out['voice']);
        $this->assertNull($out['noVoice']);
        $this->assertSame('Touchez « Payer » Ensuite', $out['speakable']);
        $this->assertSame(['id' => 'abc', 'messages' => [['from' => 'visitor', 'text' => 'Bonjour', 'fallback' => false], ['from' => 'ama', 'text' => 'Salut', 'fallback' => true]]], $out['loaded']);
        $this->assertSame(['id' => null, 'messages' => []], $out['broken']);
        $this->assertSame(['id' => null, 'messages' => []], $out['denied']);
    }
}
