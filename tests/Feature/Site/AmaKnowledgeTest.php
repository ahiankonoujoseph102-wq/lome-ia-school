<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaKnowledge;
use App\Assistant\AmaPrompt;
use App\Assistant\UnansweredReply;
use App\Catalogue\CatalogueUpdate;
use App\Enums\UserRole;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\CourseModule;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base de connaissances d'Ama : ce qu'elle reçoit avec chaque message (formations, programme réel, pages
 * publiques, règles, base de l'admin, guide), écran de l'admin, taille approximative et « Questions sans
 * réponse ». Faux Gemini (Http::fake) : aucun vrai appel.
 */
class AmaKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private const string VISITOR = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.gemini.key' => 'cle-de-test', 'services.gemini.model' => 'modele-flash-de-test']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);
    }

    private function fakeGemini(string $text = 'Bonjour.'): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => 'STOP']]])]);
    }

    private function ask(string $message = 'Bonjour'): void
    {
        $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)->postJson(route('assistant.message'), ['message' => $message])->assertOk();
    }

    private function received(): string
    {
        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded);

        /** @var Request $request */
        $request = $recorded->last()[0];

        return (string) data_get($request->data(), 'system_instruction.parts.0.text');
    }

    public function test_ama_receives_the_school_the_pages_the_rules_and_the_guide_under_clear_headings(): void
    {
        $this->fakeGemini();
        $this->ask();
        $prompt = $this->received();

        foreach ([
            '## Informations de l\'école (à jour)',
            '## Base de connaissances de l\'école (rédigée par l\'équipe)',
            '### L\'école et son fondateur',
            '## Formations proposées : prix, description et programme (à jour)',
            '## Informations des pages publiques du site',
            '### Page d\'accueil',
            '### Page « Entreprises et réalisations sur mesure »',
            '### Questions fréquentes du site',
            '### Règles importantes (conditions générales de vente)',
            '## Guide des parcours du site',
            '## Contexte de la page du visiteur',
        ] as $heading) {
            $this->assertStringContainsString($heading, $prompt, $heading);
        }

        // Fondateur et école : faits du projet, pas de témoignage ni de chiffre inventé.
        $this->assertStringContainsString('Joseph Ahiankonou, surnommé « Coach Joseph »', $prompt);
        // Lomé IA School est une école récente, distincte de Joseph Academy (écriture de livres) : jamais « ancien nom ».
        $this->assertStringContainsString('C\'est une école récente.', $prompt);
        $this->assertStringContainsString('Joseph Academy est une autre activité de Joseph', $prompt);
        $this->assertStringNotContainsString('Son ancien nom était Joseph Academy', $prompt);
        $this->assertStringNotContainsString('créée en 2023', $prompt);
        $this->assertStringContainsString('2023 année où Joseph a commencé à former', $prompt);
        $this->assertStringContainsString('plus de 1 000 personnes', $prompt);
        $this->assertStringContainsString('Adidogomé, Lycée moderne 2, Lomé, Togo', $prompt);
        $this->assertStringNotContainsString('{adresse}', $prompt);
        $this->assertStringNotContainsString('{horaires}', $prompt);

        // Pages publiques et règles des CGV.
        $this->assertStringContainsString('L\'intelligence artificielle transforme le monde du travail', $prompt);
        $this->assertStringContainsString('La création de sites web', $prompt);
        $this->assertStringContainsString('Comment se passe le paiement ?', $prompt);
        $this->assertStringContainsString('dans les 48 heures qui suivent son achat', $prompt);
        $this->assertStringContainsString('à vie', $prompt);
        $this->assertStringContainsString('Les frais de transfert sont à la charge de l\'expéditeur.', $prompt);
        $this->assertStringContainsString('## 6. Payer par Mobile Money', $prompt);
        $this->assertStringNotContainsString('{seuil}', $prompt);

        // Instructions : uniquement à partir des informations, « je n'ai pas cette information ».
        $this->assertStringContainsString('UNIQUEMENT à partir des informations ci-dessous', $prompt);
        $this->assertStringContainsString('Je n\'ai pas cette information.', $prompt);
        $this->assertStringContainsString('consultation gratuite', $prompt);
        $this->assertStringContainsString(AmaPrompt::UNKNOWN_MARKER, $prompt);
    }

    public function test_each_formation_is_sent_with_its_price_description_and_real_programme(): void
    {
        $this->fakeGemini();
        $formation = Formation::factory()->create(['title' => 'Formation Exemple Avec Contenu', 'price' => 25000, 'description' => 'Description exemple de la formation.']);
        $module = CourseModule::factory()->create(['formation_id' => $formation->id, 'title' => 'Module Exemple Bases', 'position' => 1]);
        Lesson::factory()->create(['course_module_id' => $module->id, 'title' => 'Leçon Exemple Un', 'position' => 1]);
        Lesson::factory()->create(['course_module_id' => $module->id, 'title' => 'Leçon Exemple Deux', 'position' => 2]);
        Lesson::factory()->draft()->create(['course_module_id' => $module->id, 'title' => 'Leçon Exemple Brouillon', 'position' => 3]);
        // Un module sans leçon publiée n'est pas envoyé.
        CourseModule::factory()->create(['formation_id' => $formation->id, 'title' => 'Module Exemple Vide', 'position' => 2]);

        $this->ask();
        $prompt = $this->received();

        $this->assertStringContainsString("### Formation Exemple Avec Contenu\nPrix : 25\u{00A0}000\u{00A0}FCFA", $prompt);
        $this->assertStringContainsString('Description : Description exemple de la formation.', $prompt);
        $this->assertStringContainsString('- Module Exemple Bases : Leçon Exemple Un ; Leçon Exemple Deux', $prompt);
        $this->assertStringNotContainsString('Leçon Exemple Brouillon', $prompt);
        $this->assertStringNotContainsString('Module Exemple Vide', $prompt);
    }

    public function test_a_formation_without_content_is_flagged_so_ama_describes_no_programme(): void
    {
        $this->fakeGemini();
        Formation::factory()->create(['title' => 'Formation Exemple Sans Contenu']);
        // Formation masquée : jamais envoyée.
        Formation::factory()->create(['title' => 'Formation Exemple Masquée', 'is_active' => false]);

        $this->ask();
        $prompt = $this->received();

        $this->assertMatchesRegularExpression('/### Formation Exemple Sans Contenu\n.*\nDescription : .*\nProgramme : aucun module ni aucune leçon n\'est encore publié pour cette formation\. Ne décris aucun programme/s', $prompt);
        $this->assertStringNotContainsString('Formation Exemple Masquée', $prompt);
    }

    public function test_a_lesson_added_later_is_known_at_the_next_message(): void
    {
        // La formation complète créée par la migration de novembre 2026 n'entre pas dans ce test.
        Formation::query()->where('slug', CatalogueUpdate::COMPLETE_SLUG)->delete();
        $this->fakeGemini();
        $formation = Formation::factory()->create(['title' => 'Formation Exemple Évolutive']);

        $this->ask();
        $this->assertStringContainsString('aucun module ni aucune leçon', $this->received());

        $module = CourseModule::factory()->create(['formation_id' => $formation->id, 'title' => 'Module Exemple Nouveau']);
        Lesson::factory()->create(['course_module_id' => $module->id, 'title' => 'Leçon Exemple Nouvelle']);

        $this->ask('Encore ?');
        $prompt = $this->received();
        $this->assertStringContainsString('- Module Exemple Nouveau : Leçon Exemple Nouvelle', $prompt);
        $this->assertStringNotContainsString('aucun module ni aucune leçon', $prompt);
    }

    public function test_the_admin_knowledge_base_is_sent_and_taken_into_account(): void
    {
        $this->fakeGemini();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'provider' => 'gemini',
            'knowledge_school' => 'Texte exemple : l\'école a un partenaire imaginaire.',
            'knowledge_faq' => [
                ['question' => 'Question exemple ?', 'answer' => 'Réponse exemple.'],
                ['question' => 'Question sans réponse', 'answer' => ''],
            ],
            'knowledge_other' => 'Autre information exemple.',
        ])->assertSessionHasNoErrors();

        $this->assertSame([['question' => 'Question exemple ?', 'answer' => 'Réponse exemple.']], AmaKnowledge::faq());

        $this->ask();
        $prompt = $this->received();

        $this->assertStringContainsString('Texte exemple : l\'école a un partenaire imaginaire.', $prompt);
        $this->assertStringNotContainsString('Joseph Academy', $prompt);
        $this->assertStringContainsString("### Questions fréquentes\nQuestion : Question exemple ?\nRéponse : Réponse exemple.", $prompt);
        $this->assertStringNotContainsString('Question sans réponse', $prompt);
        $this->assertStringContainsString("### Autres informations\nAutre information exemple.", $prompt);

        // Champ « L'école » vidé : retour au texte de départ. FAQ et autres informations vides : rien d'envoyé.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'provider' => 'gemini', 'knowledge_school' => ''])->assertSessionHasNoErrors();
        $this->ask('Encore ?');
        $prompt = $this->received();
        $this->assertStringContainsString('Joseph Ahiankonou', $prompt);
        $this->assertStringNotContainsString('### Questions fréquentes'."\n", $prompt);
        $this->assertStringNotContainsString('### Autres informations', $prompt);
    }

    public function test_the_default_knowledge_has_an_empty_faq_and_no_other_information(): void
    {
        $this->assertSame([], AmaKnowledge::faq());
        $this->assertSame('', AmaKnowledge::other());
        $this->assertStringContainsString('Coach Joseph', AmaKnowledge::school());
    }

    public function test_the_admin_screen_shows_the_knowledge_base_and_the_approximate_size(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Formation::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk();
        $html = html_entity_decode((string) $response->getContent(), ENT_QUOTES);

        foreach ([
            'Base de connaissances d\'Ama',
            'L\'école et son fondateur',
            'Questions fréquentes (une question, sa réponse)',
            'Autres informations',
            'Coach Joseph',
            'Ce qu\'Ama reçoit à chaque message',
            'data-test="assistant-size-total"',
            'jetons par minute',
        ] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
    }

    public function test_the_size_stays_far_under_the_google_limit_with_a_full_catalogue(): void
    {
        // Catalogue d'exemple : 17 formations, 8 modules de 6 leçons chacune.
        foreach (Formation::factory()->count(17)->create() as $formation) {
            foreach (CourseModule::factory()->count(8)->create(['formation_id' => $formation->id]) as $module) {
                Lesson::factory()->count(6)->create(['course_module_id' => $module->id]);
            }
        }

        $stats = AmaPrompt::stats();

        $this->assertGreaterThan(0, $stats['total']);
        $this->assertLessThan(AmaPrompt::GOOGLE_TOKENS_PER_MINUTE, $stats['perMinute']);
        $this->assertLessThanOrEqual(AmaKnowledge::TOKEN_BUDGET, $stats['total']);
    }

    public function test_descriptions_are_shortened_but_lesson_titles_are_kept_when_too_heavy(): void
    {
        $formation = Formation::factory()->create(['description' => str_repeat('Description très longue. ', 4000)]);
        $module = CourseModule::factory()->create(['formation_id' => $formation->id, 'title' => 'Module Exemple Lourd']);
        Lesson::factory()->create(['course_module_id' => $module->id, 'title' => 'Leçon Exemple Lourde']);

        // Visiteur sur la page de cette formation : elle est détaillée, mais sa description est raccourcie.
        $context = ['formation' => $formation->title];
        $prompt = AmaPrompt::build($context);
        AmaPrompt::sections($context, $shortened);

        $this->assertTrue($shortened);
        $this->assertLessThan(AmaKnowledge::TOKEN_BUDGET, AmaKnowledge::tokens($prompt));
        $this->assertLessThan(AmaKnowledge::TOKEN_BUDGET, AmaPrompt::stats()['total']);
        $this->assertStringContainsString('Module Exemple Lourd : Leçon Exemple Lourde', $prompt);
        $this->assertStringContainsString('…', $prompt);
    }

    public function test_the_unchanged_previous_instructions_move_to_the_new_ones(): void
    {
        $previous = trim((string) preg_replace('/^ {8}/m', '', '        Tu es Ama.'));
        $this->assertSame($previous, AmaPrompt::effectiveInstructions($previous));
        $this->assertSame(AmaPrompt::DEFAULT_INSTRUCTIONS, AmaPrompt::effectiveInstructions(''));
    }

    public function test_the_unknown_marker_is_removed_and_the_question_is_flagged(): void
    {
        $this->fakeGemini('[INCONNU] Je n\'ai pas cette information. Je vous propose la consultation gratuite.');

        $response = $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)
            ->postJson(route('assistant.message'), ['message' => 'Avez-vous un partenariat avec une université ?'])
            ->assertOk();

        $response->assertJsonPath('reply', 'Je n\'ai pas cette information. Je vous propose la consultation gratuite.');
        $this->assertStringNotContainsString('INCONNU', (string) $response->json('reply'));
        $flagged = AssistantMessage::query()->where('unanswered', true)->sole();
        $this->assertSame('model', $flagged->role);
        $this->assertStringNotContainsString('INCONNU', $flagged->content);
        $this->assertSame(1, AssistantMessage::query()->where('role', 'user')->where('unanswered', false)->count());
    }

    public function test_a_normal_reply_is_not_flagged_and_usual_phrases_are_detected_without_the_marker(): void
    {
        $this->assertFalse(UnansweredReply::inspect('La formation coûte 25 000 FCFA.')['unanswered']);
        $this->assertTrue(UnansweredReply::inspect('Désolée, je n\'ai pas cette information.')['unanswered']);
        $this->assertTrue(UnansweredReply::inspect('Je ne sais pas, écrivez-nous sur WhatsApp.')['unanswered']);
        $this->assertSame(['text' => 'Bonjour.', 'unanswered' => true], UnansweredReply::inspect("[INCONNU]\nBonjour."));

        $this->fakeGemini('Touchez « Acheter maintenant ».');
        $this->ask();
        $this->assertSame(0, AssistantMessage::query()->where('unanswered', true)->count());
    }

    public function test_the_admin_filters_conversations_without_answer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $answered = AssistantConversation::query()->create(['public_id' => (string) Str::uuid(), 'visitor_hash' => 'a', 'page' => 'accueil']);
        $answered->messages()->create(['role' => 'user', 'content' => 'Question exemple répondue']);
        $answered->messages()->create(['role' => 'model', 'content' => 'Réponse exemple.']);

        $unanswered = AssistantConversation::query()->create(['public_id' => (string) Str::uuid(), 'visitor_hash' => 'b', 'page' => 'accueil']);
        $unanswered->messages()->create(['role' => 'user', 'content' => 'Question exemple sans réponse']);
        $unanswered->messages()->create(['role' => 'model', 'content' => 'Je n\'ai pas cette information.', 'unanswered' => true]);

        $this->actingAs($admin)->get(route('admin.assistant.index'))
            ->assertOk()
            ->assertSee('Questions sans réponse (1)')
            ->assertSee('Sans réponse');

        $this->actingAs($admin)->get(route('admin.assistant.index', ['filtre' => 'sans-reponse']))
            ->assertOk()
            ->assertSee('data-test="assistant-unanswered-questions"', false)
            ->assertSee('« Question exemple sans réponse »', false)
            ->assertDontSee('Question exemple répondue')
            ->assertSee('data-test="assistant-conversation-row"', false);

        $this->assertSame(1, substr_count($this->actingAs($admin)->get(route('admin.assistant.index', ['filtre' => 'sans-reponse']))->getContent(), 'data-test="assistant-conversation-row"'));
    }
}
