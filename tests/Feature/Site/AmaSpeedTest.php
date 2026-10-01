<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaChat;
use App\Assistant\AmaKnowledge;
use App\Assistant\AmaPrompt;
use App\Enums\AssistantIncidentType;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\AssistantIncident;
use App\Models\AssistantMessage;
use App\Models\Formation;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\VoiceAssistant;
use Database\Seeders\FormationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ama plus légère, plus rapide et plus attentive (côté serveur) : poids de ce qu'elle reçoit (sous 10 000 jetons par
 * demande avec le vrai catalogue), formations et parties du guide détaillées seulement quand elles servent, réflexion
 * minimale de Gemini, mesure des durées sans donnée personnelle, phrases mal entendues jamais comptées ni transmises,
 * interruption à la voix réglée dans l'admin. Faux Gemini (Http::fake) : aucun vrai appel.
 */
class AmaSpeedTest extends TestCase
{
    use RefreshDatabase;

    private const string KEY = 'cle-de-test-GEMINI-vitesse-9z8y';

    private const string VISITOR = 'cccccccccccccccccccccccccccccccccccccccc';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.gemini.key' => self::KEY, 'services.gemini.model' => 'gemini-3.5-flash-lite']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);
    }

    private function fakeReply(string $text = 'Bonjour.'): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => 'STOP']]])]);
    }

    private function fakeStream(string $text): void
    {
        $body = 'data: '.json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]]])."\r\n\r\n";
        // Une réponse neuve à chaque appel (le flux d'une réponse ne se lit qu'une fois).
        Http::fake(['generativelanguage.googleapis.com/*' => fn () => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function write(string $message, array $data = []): array
    {
        return $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)
            ->postJson(route('assistant.message'), ['message' => $message, ...$data])->assertOk()->json();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function speak(string $message, array $data = []): array
    {
        $content = $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)
            ->post(route('assistant.call'), ['message' => $message, ...$data])->assertOk()->streamedContent();
        $lines = array_filter(explode("\n", $content), fn (string $line): bool => str_starts_with($line, 'data: '));

        return array_map(fn (string $line): array => json_decode(substr($line, 6), true), array_values($lines));
    }

    private function system(): string
    {
        /** @var Request $request */
        $request = Http::recorded()->last()[0];

        return (string) data_get($request->data(), 'system_instruction.parts.0.text');
    }

    public function test_a_request_weighs_less_than_ten_thousand_tokens_with_the_real_catalogue(): void
    {
        $this->seed(FormationSeeder::class);

        $stats = AmaPrompt::stats();

        // Demande habituelle, conversation comprise : sous 10 000 jetons (contre environ 20 000 avant).
        $this->assertLessThan(10000, $stats['perRequest']);
        $this->assertLessThan(10000, AmaKnowledge::tokens(AmaPrompt::build([], call: true)) + AmaPrompt::CONVERSATION_ALLOWANCE);
        $this->assertSame(['Instructions', 'École', 'Base de connaissances', 'Formations', 'Pages du site', 'Guide des parcours', 'Contexte de la page'], array_keys($stats['parts']));
        // Les plus gros blocs ont fondu : formations en liste compacte, guide en lignes « En bref ».
        $this->assertLessThan(1500, $stats['parts']['Formations']);
        $this->assertLessThan(1000, $stats['parts']['Guide des parcours']);
        $this->assertLessThan(AmaPrompt::GOOGLE_TOKENS_PER_MINUTE, $stats['perMinute']);
    }

    public function test_every_formation_keeps_its_exact_price_but_only_the_page_formation_is_detailed(): void
    {
        $this->seed(FormationSeeder::class);
        $formations = Formation::query()->published()->get();
        $page = $formations->firstWhere('slug', 'automatisation-ia');

        $prompt = AmaPrompt::build(['formation' => $page->title]);

        foreach ($formations as $formation) {
            $this->assertStringContainsString('- '.$formation->title.' : '.$formation->formatted_price.'.', $prompt, $formation->title);
            $this->assertStringContainsString('('.$formation->slug, $prompt);
        }

        // Détail complet pour la formation de la page seulement.
        $this->assertStringContainsString('### '.$page->title."\nPrix : ".$page->formatted_price, $prompt);
        $this->assertStringContainsString('Description : '.trim((string) preg_replace('/\s+/u', ' ', (string) $page->description)), $prompt);
        $other = $formations->firstWhere('slug', 'creation-saas-ia');
        $this->assertStringNotContainsString('### '.$other->title, $prompt);
        $this->assertStringContainsString('ne décris aucun module ni aucune leçon', $prompt);
    }

    public function test_a_formation_named_by_the_visitor_is_detailed(): void
    {
        $this->seed(FormationSeeder::class);
        $this->fakeReply();
        $chatgpt = Formation::query()->where('slug', 'chatgpt-claude-initiation-ia')->sole();
        $agents = Formation::query()->where('slug', 'creation-agents-ia')->sole();

        $first = $this->write('Combien coûte la formation ChatGPT ?');
        $this->assertStringContainsString('### '.$chatgpt->title, $this->system());
        $this->assertStringNotContainsString('### '.$agents->title, $this->system());

        // La question suivante (« et elle dure combien ? ») garde la formation nommée juste avant.
        $this->write('Et elle dure combien de temps ?', ['conversation' => $first['conversation']]);
        $this->assertStringContainsString('### '.$chatgpt->title, $this->system());

        // Un mot trop courant ne détaille rien.
        $this->assertSame([], AmaKnowledge::focus(null, 'Bonjour, je voudrais une formation sur le site'));
        $this->assertSame([$agents->title], AmaKnowledge::focus(null, 'c\'est quoi un agent IA ?'));
    }

    public function test_the_live_dates_stay_in_the_compact_list(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 0));
        $this->seed(FormationSeeder::class);

        $prompt = AmaPrompt::build([]);

        $this->assertStringContainsString('EN DIRECT sur Google Meet : du jeudi 1er octobre au mercredi 7 octobre 2026', $prompt);
    }

    public function test_the_guide_details_only_what_concerns_the_visitor_and_keeps_every_payment_fact(): void
    {
        $plain = AmaPrompt::build([]);

        // Toujours : une ligne « En bref » par partie, sans la partie « Règles pour Ama » (déjà donnée par le code).
        foreach (range(1, 12) as $number) {
            $this->assertMatchesRegularExpression('/\n## '.$number.'\. [^\n]+\nEn bref : /u', $plain);
        }
        $this->assertStringNotContainsString('## Règles pour Ama', $plain);
        $this->assertStringNotContainsString('étoile 145 étoile 1 dièse', $plain);

        // Page de paiement, ou question sur le paiement : tout le détail, codes USSD exacts compris.
        foreach ([AmaPrompt::build(['page' => 'page de commande et de paiement']), AmaPrompt::build([], recent: 'Comment payer avec Flooz ?')] as $prompt) {
            foreach (['étoile 145 étoile 1 dièse', 'étoile 155 étoile 1 dièse', 'étoile 144 étoile 2 dièse', 'étoile 880 dièse', 'étoile 855 étoile 1 dièse', 'étoile 889 dièse', '« J\'ai payé : envoyer ma capture »', '8 Mo au maximum', 'moins de 50 000 FCFA'] as $fact) {
                $this->assertStringContainsString($fact, $prompt, $fact);
            }
        }

        // Remboursement : règles exactes dès qu'on en parle.
        $this->assertStringContainsString('dans les 48 heures après l\'achat, seulement si aucune leçon n\'a été consultée', AmaPrompt::build([], recent: 'Puis-je être remboursé ?'));
        $this->assertStringContainsString('retrait dès 2 000 FCFA', AmaPrompt::build([], recent: 'Comment marche l\'affiliation ?'));
    }

    public function test_no_safety_rule_is_lost(): void
    {
        foreach ([false, true] as $call) {
            $prompt = AmaPrompt::build([], call: $call);

            foreach ([
                'Tu ne demandes jamais de code secret, de mot de passe, de code Mobile Money, de code de confirmation ni de code de récupération.',
                'tu lui dis de ne jamais le partager, même avec l\'école',
                'Tu ne promets jamais de résultat : ni revenus, ni clients, ni ventes, ni emploi.',
                'aucune promesse de revenus, de clients, de ventes, d\'emploi ou de résultat',
                'Si on te demande si tu es un humain ou une IA, tu dis clairement que tu es une assistante IA.',
                'Tu ne dis jamais qu\'un paiement est reçu, validé ou refusé',
                'Tu ne dictes jamais de numéro de paiement',
                'ne dis jamais que l\'école est certifiée, agréée, enregistrée ou reconnue par l\'État',
                'aucun faux témoignage ni faux avis',
                'Ne demande jamais de code, de mot de passe ni d\'information de paiement.',
                'Remboursement : '.config('site.legal.refund_policy'),
                'Adresse : '.config('site.contact.address'),
            ] as $rule) {
                $this->assertStringContainsString($rule, $prompt, $rule);
            }
        }
    }

    public function test_the_unchanged_previous_default_instructions_move_to_the_shorter_ones(): void
    {
        // Texte d'origine précédent (avant l'allègement), resté tel quel dans l'admin.
        $previous = (string) file_get_contents(base_path('tests/Fixtures/ama-instructions-2026-10-01.txt'));
        $this->assertSame('44acddf4fa42ecf2e43a8a255a354bb7e79ccb5c', sha1(trim($previous)));
        $this->assertSame(AmaPrompt::DEFAULT_INSTRUCTIONS, AmaPrompt::effectiveInstructions($previous));
        $this->assertLessThan(AmaKnowledge::tokens($previous), AmaKnowledge::tokens(AmaPrompt::DEFAULT_INSTRUCTIONS));
    }

    public function test_gemini_is_asked_to_think_as_little_as_possible(): void
    {
        $this->fakeReply();
        $this->write('Bonjour');

        /** @var Request $request */
        $request = Http::recorded()->last()[0];
        $this->assertSame(['thinkingConfig' => ['thinkingLevel' => 'minimal']], data_get($request->data(), 'generationConfig'));

        // Gemini 2.5 Flash : budget de réflexion 0. Réglage vidé : rien n'est envoyé.
        config(['services.gemini.model' => 'gemini-2.5-flash']);
        $this->write('Bonjour');
        $this->assertSame(['thinkingConfig' => ['thinkingBudget' => 0]], data_get(Http::recorded()->last()[0]->data(), 'generationConfig'));

        config(['services.gemini.thinking' => '']);
        $this->write('Bonjour');
        $this->assertNull(data_get(Http::recorded()->last()[0]->data(), 'generationConfig'));
    }

    public function test_a_model_that_refuses_the_thinking_setting_is_asked_again_without_it(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Thinking level is not supported for this model.']], 400)
            ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'Bonjour !']]]]]])
            ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'Encore là.']]]]]])]);

        $this->assertSame('Bonjour !', $this->write('Bonjour')['reply']);
        Http::assertSentCount(2);
        $this->assertNull(data_get(Http::recorded()->last()[0]->data(), 'generationConfig'));
        $this->assertTrue(Cache::get('gemini:no-thinking:gemini-3.5-flash-lite'));

        // Ensuite, plus d'essai inutile.
        $this->assertSame('Encore là.', $this->write('Encore ?')['reply']);
        Http::assertSentCount(3);
        $this->assertFalse(AssistantIncident::query()->whereIn('type', AssistantIncidentType::problems())->exists());
    }

    public function test_each_reply_is_timed_without_personal_data_and_shown_apart_in_the_admin(): void
    {
        $this->fakeStream('La formation coûte 25 000 FCFA. Voulez-vous la voir ?');
        $events = $this->speak('Combien coûte la formation ? Mon numéro est 90 12 34 56');
        $done = end($events);

        $timing = AssistantIncident::query()->where('type', AssistantIncidentType::ResponseTiming)->sole();
        $this->assertSame($done['timing'], $timing->id);
        $this->assertSame(['mode', 'prepare', 'first', 'total', 'tokens', 'thinking'], array_keys((array) $timing->timings));
        $this->assertSame('appel', $timing->timings['mode']);
        $this->assertIsInt($timing->timings['first']);
        $this->assertIsInt($timing->timings['total']);
        $this->assertSame('niveau minimal', $timing->timings['thinking']);
        $this->assertNull($timing->detail);
        $this->assertStringNotContainsString('90 12', (string) json_encode($timing->timings));

        // Le téléphone ajoute ses durées (attente du silence, premier morceau reçu, début de la voix) : des nombres seulement.
        $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)
            ->postJson(route('assistant.timing'), ['timing' => $timing->id, 'conversation' => $done['conversation'], 'pause' => 1800, 'first' => 950, 'voice' => 1400, 'text' => 'secret'])
            ->assertOk()->assertJson(['ok' => true]);
        $timing->refresh();
        $this->assertSame(1800, $timing->timings['client_pause']);
        $this->assertSame(1400, $timing->timings['client_voice']);
        $this->assertStringNotContainsString('secret', (string) json_encode($timing->timings));

        // Un autre visiteur ne peut rien ajouter.
        $this->withCredentials()->withCookie('ama_visitor', str_repeat('d', 40))
            ->postJson(route('assistant.timing'), ['timing' => $timing->id, 'conversation' => $done['conversation'], 'voice' => 1])
            ->assertOk()->assertJson(['ok' => false]);

        // Admin : section « Durées de réponse », sans gonfler les incidents.
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $html = html_entity_decode((string) $this->actingAs($admin)->get(route('admin.assistant.index'))->assertOk()->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('Durées de réponse aujourd\'hui', $html);
        $this->assertStringContainsString('Début de la voix d\'Ama après l\'envoi (appel)', $html);
        $this->assertStringContainsString('1,4 s', $html);
        $this->assertStringNotContainsString('Mesure des durées de réponse : 1', $html);
        $this->assertMatchesRegularExpression('/Incidents aujourd\'hui<\/p>\s*<p[^>]*>0<\/p>/', $html);
    }

    public function test_a_misheard_sentence_never_reaches_gemini_nor_counts(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', 'visitor_daily_limit' => 1]]);
        Http::fake();

        foreach (['', 'euh', 'hum… bah', 'a'] as $heard) {
            $events = $this->speak($heard);
            $this->assertSame(AmaPrompt::MISHEARD_REPLY, $events[0]['notice'], $heard);
            $this->assertTrue($events[0]['misheard']);
        }

        Http::assertNothingSent();
        $this->assertSame(0, AssistantMessage::query()->count());
        $this->assertFalse(AmaChat::isMisheard('oui'));
        $this->assertFalse(AmaChat::isMisheard('non merci'));
    }

    public function test_when_gemini_did_not_understand_ama_asks_to_repeat_and_the_message_does_not_count(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', 'visitor_daily_limit' => 1]]);
        $this->fakeStream(AmaPrompt::MISHEARD_REPLY);

        $this->speak('je voudrais d\'abord savoir');
        $events = $this->speak('oui d\'accord bien d\'abord je voudrais d\'abord savoir');
        $done = end($events);

        $this->assertTrue($done['misheard']);
        $this->assertNull($done['handoff']);
        $this->assertFalse((bool) AssistantMessage::query()->where('role', AssistantMessage::AMA)->where('unanswered', true)->exists());
        // Deux phrases mal entendues : la limite de 1 message par jour n'est toujours pas atteinte.
        $this->assertSame(2, AssistantMessage::query()->where('role', AssistantMessage::VISITOR)->where('misheard', true)->count());
        $this->fakeStream('L\'intelligence artificielle, ce sont des programmes qui apprennent.');
        $events = $this->speak('c\'est quoi l\'intelligence artificielle');
        $this->assertFalse(end($events)['fallback']);

        // La règle est donnée à Gemini en appel : demander de répéter, jamais transmettre au fondateur pour ça.
        $call = AmaPrompt::build([], call: true);
        $this->assertStringContainsString('Phrase mal entendue', $call);
        $this->assertStringContainsString(AmaPrompt::MISHEARD_REPLY, $call);
        $this->assertStringContainsString('ni pour une phrase mal entendue ou coupée en appel', $call);
        $this->assertStringNotContainsString('Phrase mal entendue', AmaPrompt::build([]));
    }

    public function test_an_interrupted_visitor_gets_a_direct_answer_without_patience_phrases(): void
    {
        $this->fakeStream('Bien sûr, voici le prix.');

        $this->speak('Combien ça coûte ?', ['interrupted' => true]);
        $this->assertStringContainsString('La personne vient de te couper la parole', $this->system());
        $this->assertStringContainsString('Ne dis jamais « patientez »', $this->system());

        $this->speak('Et ensuite ?');
        $this->assertStringNotContainsString('La personne vient de te couper la parole', $this->system());

        // À l'écrit, jamais.
        $this->fakeReply();
        $this->write('Bonjour', ['interrupted' => true]);
        $this->assertStringNotContainsString('La personne vient de te couper la parole', $this->system());
    }

    public function test_the_admin_switches_voice_interruption_on_and_sees_the_weights(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->seed(FormationSeeder::class);

        $html = html_entity_decode((string) $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()->getContent(), ENT_QUOTES);
        foreach (['Interruption à la voix (expérimental)', 'data-test="voice-assistant-voice_interrupt"', 'data-test="assistant-size-parts"', 'Guide des parcours', 'Détail d\'une formation (le plus lourd', 'Guide détaillé en entier', 'Règles du mode appel', 'Cas le plus lourd'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertFalse(VoiceAssistant::voiceInterrupt());
        $this->assertStringContainsString('\\u0022voiceInterrupt\\u0022:false', (string) $this->get(route('home'))->getContent());

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'gemini', 'enabled' => '1', 'voice_interrupt' => '1', 'proactive' => '1', 'help_delay' => '90'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(VoiceAssistant::voiceInterrupt());
        $this->assertTrue(AdminActivity::query()->where('description', 'like', '%interruption à la voix : oui%')->exists());
        $page = (string) $this->get(route('home'))->getContent();
        $this->assertStringContainsString('\\u0022voiceInterrupt\\u0022:true', $page);
        $this->assertMatchesRegularExpression('#timingEndpoint\\\\u0022:\\\\u0022[^,]*assistante\\\\+/duree#', $page);

        // Éteint à nouveau, sans nouvelle mise en ligne.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'gemini', 'enabled' => '1', 'voice_interrupt' => '0', 'help_delay' => '90'])
            ->assertSessionHasNoErrors();
        $this->assertFalse(VoiceAssistant::voiceInterrupt());
    }

    public function test_the_call_hint_keeps_the_touch_text_when_the_switch_is_off(): void
    {
        $script = (string) file_get_contents(resource_path('js/site.js'));

        $this->assertStringContainsString("'Touchez le cercle pour lui couper la parole.'", $script);
        $this->assertStringContainsString("'Parlez ou touchez le cercle pour lui couper la parole.'", $script);
        $this->assertStringContainsString('config.voiceInterrupt && !this.bargeInUnavailable', $script);
        $this->assertStringContainsString('bargeIn: Boolean(config.voiceInterrupt)', $script);
    }
}
