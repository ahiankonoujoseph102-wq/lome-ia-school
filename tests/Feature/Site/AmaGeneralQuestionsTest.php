<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaPrompt;
use App\Models\AssistantMessage;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ama répond elle-même aux questions de culture générale sur l'IA (« c'est quoi l'intelligence artificielle ? »),
 * s'en tient à sa base de connaissances pour l'école, refuse le hors sujet en une phrase ; « Transmettre au
 * fondateur » ne concerne que les questions sur l'école. À l'écrit comme en appel.
 */
class AmaGeneralQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private const string VISITOR = 'cccccccccccccccccccccccccccccccccccccccc';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.gemini.key' => 'cle-de-test', 'services.gemini.model' => 'modele-flash-de-test']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);
    }

    /**
     * @return list<array{0: bool}>
     */
    public static function modes(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('modes')]
    public function test_the_three_kinds_of_questions_are_explained_to_ama(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        // Cas 1 : culture générale, réponse de l'assistante elle-même, avec un exemple, puis la formation qui correspond.
        $this->assertStringContainsString('1. Culture générale sur l\'IA et sur les sujets des formations', $prompt);
        foreach (['ChatGPT', 'Claude', 'prompt', 'agent IA', 'automatisation', 'création de contenu', 'e-commerce numérique'] as $topic) {
            $this->assertStringContainsString($topic, $prompt);
        }
        $this->assertStringContainsString('réponds toi-même avec tes connaissances', $prompt);
        $this->assertStringContainsString('avec un exemple concret', $prompt);
        $this->assertStringContainsString('propose sans insister la formation de la liste qui correspond', $prompt);

        // Cas 2 : l'école, uniquement à partir de la base de connaissances, sans jamais rien inventer.
        $this->assertStringContainsString('2. Questions sur l\'école (prix, dates, paiement, contacts, programme', $prompt);
        $this->assertStringContainsString('N\'invente jamais un prix, une date, une durée, un programme, un témoignage ni une promesse de revenus.', $prompt);
        $this->assertStringContainsString('propose de transmettre la question à Coach Joseph', $prompt);

        // Cas 3 : hors sujet, refus poli en une phrase, retour à l'IA et à l'école.
        $this->assertStringContainsString('3. Hors sujet (politique, santé, droit', $prompt);
        $this->assertStringContainsString('refuse poliment en une seule phrase', $prompt);
        $this->assertStringContainsString('reviens à l\'IA et à l\'école', $prompt);
    }

    #[DataProvider('modes')]
    public function test_general_answers_never_claim_to_come_from_the_programme_and_promise_nothing(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('Tu ne dis jamais que cette explication vient du programme d\'une formation', $prompt);
        $this->assertStringContainsString('tu ne décris pas son contenu et tu ne promets aucun résultat ni revenu', $prompt);
        $this->assertStringContainsString('Tu ne dis jamais que cette réponse vient du programme d\'une formation', $prompt);
        $this->assertStringContainsString('Tu ne promets jamais de résultat : ni revenus, ni clients, ni ventes, ni emploi.', $prompt);
    }

    #[DataProvider('modes')]
    public function test_the_founder_handoff_only_applies_to_questions_about_the_school(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('Cette règle ne s\'applique qu\'aux questions sur l\'école (cas 2)', $prompt);
        $this->assertStringContainsString('Jamais pour une question de culture générale sur l\'IA (cas 1), à laquelle tu réponds toi-même, ni pour un hors sujet (cas 3)', $prompt);
        $this->assertStringContainsString('sans proposer de transmettre la question à Coach Joseph', $prompt);
        // Le repère « sans réponse » ne s'écrit jamais pour une culture générale ni un refus.
        $this->assertStringContainsString('jamais pour une question de culture générale ni pour un refus de hors sujet', $prompt);
        // L'ancienne règle qui faisait refuser toute question sans rapport direct avec l'école a disparu.
        $this->assertStringNotContainsString('Tu refuses poliment les questions sans rapport avec Lomé IA School', $prompt);
        $this->assertStringNotContainsString('Quand tu n\'as pas la réponse, ou pour un cas particulier, dis-le honnêtement', $prompt);
    }

    public function test_the_call_mode_asks_for_one_to_three_short_sentences_on_general_questions(): void
    {
        $call = AmaPrompt::build([], call: true);
        $written = AmaPrompt::build([], call: false);

        $this->assertStringContainsString('Question de culture générale en mode appel', $call);
        $this->assertStringContainsString('« c\'est quoi l\'intelligence artificielle ? »', $call);
        $this->assertStringContainsString('en une à trois phrases courtes avec un exemple concret', $call);
        $this->assertStringContainsString('Ne propose pas de transmettre la question à Coach Joseph', $call);
        $this->assertStringNotContainsString('Question de culture générale en mode appel', $written);
        // À l'écrit : un court paragraphe.
        $this->assertStringContainsString('un court paragraphe pour une question de culture générale', $written);
        $this->assertStringContainsString('un court paragraphe à l\'écrit', $written);
    }

    /**
     * Joseph a peut-être gardé ou modifié l'ancien texte dans l'admin : la règle ajoutée par le code prime
     * et vient après lui.
     */
    public function test_a_stored_old_style_instruction_cannot_block_general_answers(): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'instructions' => "Tu es Ama. Tu réponds UNIQUEMENT à partir des informations ci-dessous.\nTu refuses poliment les questions sans rapport avec Lomé IA School.",
        ]]);

        $prompt = AmaPrompt::build([]);

        $this->assertStringContainsString('Tu refuses poliment les questions sans rapport avec Lomé IA School.', $prompt);
        $this->assertStringContainsString('elle prime sur toute autre consigne qui te limiterait aux informations de l\'école ou te ferait refuser une question', $prompt);
        $this->assertGreaterThan(
            strpos($prompt, 'Tu refuses poliment les questions sans rapport'),
            strpos($prompt, 'Règle des trois sortes de questions'),
        );
    }

    public function test_the_previous_default_text_is_replaced_by_the_new_one(): void
    {
        // Texte d'origine précédent, recopié tel quel dans l'admin : il est remplacé par le texte actuel.
        $previous = "Tu es Ama, l'assistante IA de Lomé IA School, une école de formations en intelligence artificielle basée à Lomé, au Togo. Tu aides les visiteurs et les élèves du site : connaître l'école et son fondateur, choisir une formation, créer un compte, confirmer l'adresse e-mail, payer par Mobile Money, accéder aux cours, réserver la consultation gratuite et comprendre l'affiliation.\n\nTon style :\n- Chaleureuse, polie et patiente. Tu vouvoies la personne.\n- Des phrases courtes et simples, en français. Trois ou quatre phrases au maximum par réponse.\n- Une seule étape à la fois, puis tu demandes si c'est fait avant de passer à la suivante.\n- Tu reprends les mots exacts des boutons du site, entre guillemets « ».\n- Tes réponses peuvent être lues à voix haute : pas de Markdown, pas d'émojis, pas de symboles, pas de longues adresses.\n\nTes règles :\n- Tu réponds UNIQUEMENT à partir des informations ci-dessous : l'école et son fondateur, la base de connaissances, la liste des formations avec leurs prix et leur programme, les pages du site, le guide du site et le contexte de la page. Tu n'inventes jamais rien : ni prix, ni durée, ni nombre de leçons, ni contenu de formation, ni bonus, ni date, ni chiffre, ni avis, ni témoignage.\n- Pour une formation dont le programme n'est pas publié, tu ne décris aucun contenu : tu dis que le programme détaillé n'est pas encore affiché sur le site.\n- Quand la réponse n'est pas dans ces informations, tu le dis honnêtement : « Je n'ai pas cette information. » Tu ne devines pas. Tu proposes ensuite de transmettre la question à notre fondateur, Coach Joseph, qui répondra personnellement (voir la règle « Transmettre au fondateur »).\n- Quand on te demande comment joindre l'école ou son fondateur (numéro, WhatsApp, e-mail, adresse, horaires), tu donnes les coordonnées publiques des « Informations de l'école ».\n- Tu ne promets jamais de résultat : ni revenus, ni clients, ni ventes, ni emploi.\n- Si on te demande si tu es un humain ou une IA, tu dis clairement que tu es une assistante IA.\n- Tu ne demandes jamais de code secret, de mot de passe, de code Mobile Money, de code de confirmation ni de code de récupération. Si la personne en écrit un, tu lui dis de ne jamais le partager, même avec l'école.\n- Tu ne demandes aucune donnée personnelle, sauf pour transmettre une question à Coach Joseph : le prénom et, au choix de la personne, son adresse e-mail ou son numéro WhatsApp.\n- Tu ne dis jamais qu'un paiement est reçu, validé ou refusé : seule l'équipe le vérifie. Tu expliques où suivre son achat, dans « Mes achats ».\n- Tu ne dictes jamais de numéro de paiement : le numéro exact et le montant s'affichent à l'étape 3 du paiement, avec le bouton « Copier le numéro ».\n- Tu refuses poliment les questions sans rapport avec Lomé IA School, ses formations ou l'utilisation du site, et tu proposes ce que tu peux faire.\n- Pour un cas particulier (remboursement, paiement non reçu, accès manquant, problème de compte), tu proposes d'écrire à l'équipe sur WhatsApp ou de transmettre la question à Coach Joseph.";

        $this->assertSame(AmaPrompt::DEFAULT_INSTRUCTIONS, AmaPrompt::effectiveInstructions($previous));
        $this->assertSame(AmaPrompt::DEFAULT_INSTRUCTIONS, AmaPrompt::effectiveInstructions(''));
        // Un texte modifié à la main n'est jamais réécrit.
        $this->assertSame('Texte de Joseph.', AmaPrompt::effectiveInstructions('Texte de Joseph.'));
    }

    public function test_a_general_question_is_sent_with_the_rules_and_its_answer_is_not_an_unanswered_question(): void
    {
        $answer = 'L\'intelligence artificielle, ce sont des programmes qui apprennent à partir d\'exemples. Par exemple, votre téléphone propose le mot suivant quand vous écrivez un message. Si vous voulez apprendre à vous en servir, la formation « ChatGPT & Claude » peut vous intéresser.';
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $answer]]], 'finishReason' => 'STOP']]])]);

        $response = $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)
            ->postJson(route('assistant.message'), ['message' => 'C\'est quoi l\'intelligence artificielle ?'])
            ->assertOk();

        $this->assertSame($answer, $response->json('reply'));
        $this->assertStringNotContainsString('transmettre', (string) $response->json('reply'));
        $this->assertFalse(AssistantMessage::query()->where('role', AssistantMessage::AMA)->sole()->unanswered);

        Http::assertSent(function (Request $request): bool {
            $system = (string) data_get($request->data(), 'system_instruction.parts.0.text');

            return str_contains($system, 'Règle des trois sortes de questions')
                && str_contains($system, '1. Culture générale sur l\'IA')
                && ! str_contains($system, 'Mode appel : la personne');
        });
    }
}
