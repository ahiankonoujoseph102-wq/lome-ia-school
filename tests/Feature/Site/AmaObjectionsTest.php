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
 * Ama répond d'abord aux objections des prospects avec les faits vrais qu'elle a (confiance, prix, temps, débutant,
 * résultats, YouTube, réflexion, accès), avant de transmettre quoi que ce soit au fondateur ; elle n'invente ni
 * témoignage, ni chiffre, ni urgence, ni certification. À l'écrit comme en appel.
 */
class AmaObjectionsTest extends TestCase
{
    use RefreshDatabase;

    private const string VISITOR = 'dddddddddddddddddddddddddddddddddddddddd';

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
    public function test_every_kind_of_objection_is_listed(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('Règle « Objections »', $prompt);
        foreach ([
            'Qu\'est-ce qui prouve que ce n\'est pas une arnaque ?',
            'c\'est trop cher',
            'je n\'ai pas le temps',
            'je suis débutant',
            'quels résultats ?',
            'pourquoi payer alors que tout est gratuit sur YouTube ?',
            'je dois réfléchir',
            'et si je ne reçois pas mon accès ?',
        ] as $objection) {
            $this->assertStringContainsString($objection, $prompt);
        }
    }

    #[DataProvider('modes')]
    public function test_ama_answers_with_true_facts_before_any_handoff(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('Une objection n\'est jamais une question sans réponse', $prompt);
        $this->assertStringContainsString('Ne commence jamais par « Je n\'ai pas cette information » et ne propose pas de transmettre à Coach Joseph en premier', $prompt);
        // Ordre : reconnaître, répondre avec des faits, prochaine étape ; le fondateur seulement ensuite.
        $this->assertStringContainsString('(1) reconnais la préoccupation en une phrase', $prompt);
        $this->assertStringContainsString('(2) réponds calmement avec un ou deux faits vrais', $prompt);
        $this->assertStringContainsString('(3) propose une prochaine étape simple', $prompt);
        $this->assertStringContainsString('Tu ne transmets la question à Coach Joseph qu\'ensuite', $prompt);
        // La règle de transmission renvoie à la règle des objections.
        $this->assertStringContainsString('Face à une objection (règle « Objections »), tu réponds d\'abord toi-même', $prompt);
        // Une réponse à une objection n'est pas comptée parmi les questions sans réponse.
        $this->assertStringContainsString('ni pour une réponse à une objection construite avec les faits que tu as', $prompt);
        // La règle des objections est avant celle de la transmission.
        $this->assertLessThan(strpos($prompt, 'Règle « Transmettre au fondateur »'), strpos($prompt, 'Règle « Objections »'));
    }

    #[DataProvider('modes')]
    public function test_the_facts_ama_may_use_are_really_in_what_she_receives(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        // Les faits cités par la règle existent bien dans les réglages envoyés avec elle.
        $this->assertStringContainsString('Adresse : '.config('site.contact.address'), $prompt);
        $this->assertStringContainsString('Horaires : '.config('site.contact.hours'), $prompt);
        $this->assertStringContainsString('Remboursement : '.config('site.legal.refund_policy'), $prompt);
        $this->assertStringContainsString('Accès : '.config('site.legal.access_duration'), $prompt);
        $this->assertStringContainsString('Consultation gratuite de 30 minutes', $prompt);
        $this->assertStringContainsString('remboursement sous 48 heures si aucune leçon n\'a été consultée', $prompt);
    }

    #[DataProvider('modes')]
    public function test_strict_prohibitions_are_in_the_prompt(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('aucun faux témoignage ni faux avis', $prompt);
        $this->assertStringContainsString('aucun chiffre, aucune durée, aucun nombre d\'élèves ni de résultats inventés', $prompt);
        $this->assertStringContainsString('aucune promesse de revenus, de clients, de ventes, d\'emploi ou de résultat', $prompt);
        $this->assertStringContainsString('aucune fausse urgence ni fausse rareté', $prompt);
        $this->assertStringContainsString('le site annonce des places non limitées', $prompt);
        $this->assertStringContainsString('aucune pression', $prompt);
    }

    #[DataProvider('modes')]
    public function test_ama_never_claims_a_certification_and_says_the_school_is_recent(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('ne dis jamais que l\'école est certifiée, agréée, enregistrée ou reconnue par l\'État', $prompt);
        $this->assertStringContainsString('l\'école est récente et n\'a pas encore de certification officielle', $prompt);
        $this->assertStringContainsString('attestation de participation délivrée par Lomé IA School : jamais un diplôme, ni un certificat reconnu', $prompt);
        $this->assertStringContainsString('« Agents IA »', $prompt);
    }

    public function test_missing_testimonials_are_admitted_not_invented(): void
    {
        $prompt = AmaPrompt::build([]);

        $this->assertStringContainsString('tu n\'as pas de témoignage à citer pour le moment', $prompt);
        $this->assertStringContainsString('sans rien inventer, puis propose la consultation gratuite', $prompt);
    }

    public function test_the_length_is_one_short_paragraph_in_writing_and_one_to_three_sentences_on_the_phone(): void
    {
        $written = AmaPrompt::build([], call: false);
        $call = AmaPrompt::build([], call: true);

        $this->assertStringContainsString('un court paragraphe à l\'écrit ; en appel, une à trois phrases courtes', $written);
        $this->assertStringNotContainsString('Objection en mode appel', $written);
        $this->assertStringContainsString('Objection en mode appel', $call);
        $this->assertStringContainsString('en trois phrases courtes au plus, sans liste ni chiffre inventé', $call);
        $this->assertStringContainsString('Ne propose pas de transmettre à Coach Joseph avant d\'avoir répondu', $call);
    }

    /**
     * Un texte enregistré dans l'admin (ancien ou modifié) ne peut pas supprimer la règle : elle est ajoutée par
     * le code, après lui.
     */
    public function test_a_stored_instruction_cannot_remove_the_objection_rule(): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'instructions' => 'Tu es Ama. Quand tu n\'as pas la réponse, tu transmets à Coach Joseph.',
        ]]);

        $prompt = AmaPrompt::build([]);

        $this->assertGreaterThan(
            strpos($prompt, 'Quand tu n\'as pas la réponse, tu transmets à Coach Joseph.'),
            strpos($prompt, 'Règle « Objections »'),
        );
        $this->assertStringContainsString('elle prime sur toute autre consigne', $prompt);
    }

    /**
     * Cas réel : « qu'est-ce qui prouve que ce n'est pas une arnaque ? » — la demande part avec la règle, et une
     * réponse faite de faits vrais n'est ni « sans réponse » ni une proposition de transmettre.
     */
    #[DataProvider('modes')]
    public function test_the_real_scam_objection_is_sent_with_the_rule_and_an_answer_with_facts_is_not_unanswered(bool $call): void
    {
        $answer = 'Je comprends cette inquiétude, c\'est normal d\'être prudent. L\'école a une adresse à Lomé, des horaires et un WhatsApp publics, et la consultation gratuite permet de parler au fondateur en vidéo avant d\'acheter. Voulez-vous la réserver ?';
        $candidate = ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $answer]]], 'finishReason' => 'STOP']]];
        Http::fake(['generativelanguage.googleapis.com/*' => $call
            ? Http::response('data: '.json_encode($candidate)."\r\n\r\n", 200, ['Content-Type' => 'text/event-stream'])
            : Http::response($candidate)]);

        $request = $this->withCredentials()->withCookie('ama_visitor', self::VISITOR);
        $question = 'Qu\'est-ce qui prouve que ce n\'est pas une arnaque ?';

        if ($call) {
            $content = $request->post(route('assistant.call'), ['message' => $question], ['Accept' => 'application/x-ndjson'])
                ->assertOk()->streamedContent();
            $this->assertStringContainsString('Je comprends cette inquiétude', $content);
            $this->assertStringNotContainsString('transmettre', $content);
        } else {
            $response = $request->postJson(route('assistant.message'), ['message' => $question])->assertOk();
            $this->assertSame($answer, $response->json('reply'));
            $this->assertStringNotContainsString('transmettre', (string) $response->json('reply'));
        }

        $this->assertFalse(AssistantMessage::query()->where('role', AssistantMessage::AMA)->sole()->unanswered);

        Http::assertSent(function (Request $sent) use ($call): bool {
            $system = (string) data_get($sent->data(), 'system_instruction.parts.0.text');

            return str_contains($system, 'Règle « Objections »')
                && str_contains($system, 'ne dis jamais que l\'école est certifiée')
                && str_contains($system, 'Mode appel : la personne') === $call
                && str_contains($system, 'Objection en mode appel') === $call;
        });
    }

    public function test_the_guide_explains_the_objection_rule(): void
    {
        $guide = (string) file_get_contents(base_path(AmaPrompt::GUIDE_PATH));

        $this->assertStringContainsString('**Objections**', $guide);
        $this->assertStringContainsString('Je comprends cette inquiétude', $guide);
        $this->assertStringContainsString('places non limitées', $guide);
        $this->assertStringContainsString('n\'a pas encore de certification officielle', $guide);
        $this->assertStringContainsString('attestation de participation, jamais un diplôme', $guide);
        $this->assertStringContainsString('aucune fausse urgence', $guide);
        // La partie « Règles pour Ama » du guide (pour l'agent ElevenLabs) n'est pas renvoyée à Gemini : la même règle
        // lui est déjà donnée par le code, une seule fois.
        $prompt = AmaPrompt::build([]);
        $this->assertStringNotContainsString('**Objections**', $prompt);
        $this->assertSame(1, substr_count($prompt, 'Règle « Objections »'));
        $this->assertStringContainsString('## Guide des parcours du site', $prompt);
    }
}
