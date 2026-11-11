<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaKnowledge;
use App\Assistant\AmaPrompt;
use App\Catalogue\CatalogueUpdate;
use App\Models\AssistantMessage;
use App\Models\CourseModule;
use App\Models\Formation;
use App\Models\Lesson;
use App\Settings\SiteSettings;
use App\Support\Money;
use Database\Seeders\FormationSeeder;
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

    /**
     * Poids (jetons estimés) de la règle « Objections » avant l'ajout de la règle « Ton » et des nouvelles objections.
     */
    private const int OBJECTION_RULES_BEFORE = 940;

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
            'je n\'ai pas d\'ordinateur',
            'vais-je gagner de l\'argent ?',
            'le paiement Mobile Money est compliqué',
            'méfiance envers l\'IA',
            'je n\'ai pas d\'argent maintenant',
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
        $this->assertStringContainsString('(3) propose une seule prochaine étape simple (le lien de la page de la formation concernée, la consultation gratuite, ou expliquer le paiement étape par étape), pas à chaque message', $prompt);
        $this->assertStringContainsString('Tu ne transmets la question à Coach Joseph qu\'ensuite, et seulement si une vraie question reste sans réponse', $prompt);
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
        $this->assertStringContainsString('aucun dénigrement ni aucune comparaison mensongère avec YouTube ou un concurrent', $prompt);
        $this->assertStringContainsString('Tu ne valides jamais un paiement : seule l\'équipe le vérifie.', $prompt);
        // Jamais de rareté : « places » n'apparaît que sous la forme « places non limitées ».
        $this->assertSame(substr_count($prompt, 'places'), substr_count($prompt, 'places non limitées'));
    }

    #[DataProvider('modes')]
    public function test_ama_never_claims_a_certification_and_says_the_school_is_recent(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('ne dis jamais que l\'école est certifiée, agréée, enregistrée ou reconnue par l\'État', $prompt);
        $this->assertStringContainsString('l\'école est récente et n\'a pas encore de certification officielle', $prompt);
        // Novembre 2026 : attestation de fin de formation pour chaque formation payante, stage et recrutement possibles.
        $this->assertStringContainsString('attestation de fin de formation délivrée par Lomé IA School : jamais un diplôme, ni un certificat reconnu', $prompt);
        $this->assertStringContainsString('jamais une promesse d\'emploi', $prompt);
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
        $this->assertStringContainsString('attestation de fin de formation, jamais un diplôme', $guide);
        $this->assertStringContainsString('aucune fausse urgence', $guide);
        // La partie « Règles pour Ama » du guide (pour l'agent ElevenLabs) n'est pas renvoyée à Gemini : la même règle
        // lui est déjà donnée par le code, une seule fois.
        $prompt = AmaPrompt::build([]);
        $this->assertStringNotContainsString('**Objections**', $prompt);
        $this->assertSame(1, substr_count($prompt, 'Règle « Objections »'));
        $this->assertStringContainsString('## Guide des parcours du site', $prompt);
    }

    /**
     * Repères chiffrés lus dans la base et les réglages, jamais écrits en dur : formation la moins chère et seuil
     * d'accès provisoire (un changement dans l'admin est connu d'Ama tout de suite).
     */
    #[DataProvider('modes')]
    public function test_the_cheapest_formation_and_the_threshold_come_from_the_database_and_the_settings(bool $call): void
    {
        Formation::factory()->create(['title' => 'Formation Exemple Chère', 'price' => 36000]);
        Formation::factory()->create(['title' => 'Formation Exemple Petit Prix', 'price' => 5100]);
        Formation::factory()->create(['title' => 'Formation Exemple Masquée', 'price' => 1000, 'is_active' => false]);

        $prompt = AmaPrompt::build([], call: $call);
        $this->assertStringContainsString('Formations à partir de '.Money::fcfa(5100).' (« Formation Exemple Petit Prix »).', $prompt);
        $this->assertStringContainsString('Formation ouverte tout de suite, à titre provisoire, sous '.Money::fcfa(50000).' payés', $prompt);
        $this->assertStringContainsString('paiement Mobile Money en 3 étapes guidées', $prompt);

        SiteSettings::save(['site.payments.verification_threshold' => 40000]);
        $this->assertStringContainsString('sous '.Money::fcfa(40000).' payés', AmaPrompt::build([], call: $call));
    }

    public function test_without_any_visible_formation_no_starting_price_is_given(): void
    {
        // La formation complète créée par la migration de novembre 2026 n'entre pas dans ce test.
        Formation::query()->where('slug', CatalogueUpdate::COMPLETE_SLUG)->delete();
        $this->assertStringNotContainsString('Formations à partir de', AmaPrompt::build([]));
    }

    /**
     * Visiteur sans moyens : aucune insistance, solutions gratuites ou la moins chère, jamais d'endettement.
     */
    #[DataProvider('modes')]
    public function test_a_visitor_without_money_is_never_pushed(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('Pas les moyens, pas d\'argent maintenant ou difficulté financière : aucune insistance ni relance.', $prompt);
        $this->assertStringContainsString('Propose les leçons en aperçu gratuit s\'il y en a, la consultation gratuite ou la formation la moins chère qui correspond à son besoin, et dis qu\'il peut revenir quand il veut.', $prompt);
        $this->assertStringContainsString('N\'encourage jamais à emprunter ni à s\'endetter.', $prompt);
    }

    /**
     * Les leçons en aperçu gratuit sont signalées à Ama (elle peut les proposer) ; sans aperçu, aucune mention.
     */
    public function test_free_preview_lessons_are_marked_for_ama(): void
    {
        $formation = Formation::factory()->create(['title' => 'Formation Exemple Aperçu', 'price' => 15500]);
        $module = CourseModule::factory()->create(['formation_id' => $formation->id, 'title' => 'Module Exemple', 'position' => 1]);
        Lesson::factory()->preview()->create(['course_module_id' => $module->id, 'title' => 'Leçon Exemple Ouverte', 'position' => 1]);
        Lesson::factory()->create(['course_module_id' => $module->id, 'title' => 'Leçon Exemple Fermée', 'position' => 2]);

        $text = AmaKnowledge::formations(null, ['Formation Exemple Aperçu']);
        $this->assertStringContainsString('programme publié : 2 leçons, dont 1 en aperçu gratuit', $text);
        $this->assertStringContainsString('Leçon Exemple Ouverte (aperçu gratuit) ; Leçon Exemple Fermée', $text);
        $this->assertStringNotContainsString('Leçon Exemple Fermée (aperçu gratuit)', $text);

        Lesson::query()->update(['is_preview' => false]);
        $this->assertStringNotContainsString('aperçu gratuit', AmaKnowledge::formations(null, ['Formation Exemple Aperçu']));
    }

    /**
     * Objections courantes, réponses simulées faites de faits vrais (faux Gemini) : chaque demande part avec les
     * règles « Ton » et « Objections », et la réponse n'est ni notée « sans réponse », ni une proposition de
     * transmettre au fondateur (aucune carte). Le vrai Gemini n'est pas vérifiable ici.
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function objectionScenarios(): array
    {
        $scenarios = [
            'trop cher' => ['C\'est trop cher pour moi.', 'Je comprends, le budget compte. Les formations ont des prix différents et le prix est affiché avant de payer. Voulez-vous que je vous indique la moins chère qui correspond à votre besoin ?'],
            'pas le temps' => ['Je n\'ai pas le temps de suivre une formation.', 'Je comprends, le temps est précieux. Les formations sont enregistrées : vous avancez à votre rythme, avec un accès à vie.'],
            'sans ordinateur' => ['Je suis débutant et je n\'ai pas d\'ordinateur.', 'C\'est une question importante. Le site et les cours s\'ouvrent aussi sur téléphone. La consultation gratuite permet de vérifier ensemble ce qui vous convient.'],
            'gagner de l\'argent' => ['Est-ce que je vais gagner de l\'argent ?', 'Je comprends la question. La formation vous apprend à faire les choses vous-même ; je ne peux promettre aucun revenu, cela dépend de chacun.'],
            'YouTube' => ['Pourquoi payer alors que c\'est gratuit sur YouTube ?', 'Bonne question. YouTube a de bonnes vidéos ; ici, vous avez un parcours organisé, un accompagnement à vie et un groupe réservé aux élèves.'],
            'peur de ne pas recevoir l\'accès' => ['Et si je paie et que je ne reçois rien ?', 'C\'est normal d\'être prudent. Le suivi de votre achat est dans « Mes achats », et un remboursement est possible sous 48 heures si aucune leçon n\'a été consultée.'],
            'Mobile Money difficile' => ['Le paiement Mobile Money c\'est compliqué pour moi.', 'Pas d\'inquiétude, le paiement se fait en 3 étapes guidées, avec « Copier le numéro ». Voulez-vous que je vous l\'explique étape par étape ?'],
            'méfiance envers l\'IA' => ['Je me méfie de l\'IA.', 'C\'est une prudence saine. L\'IA est un outil : elle propose, et c\'est vous qui gardez le contrôle.'],
            'pas d\'argent maintenant' => ['Je n\'ai pas d\'argent maintenant.', 'Je comprends tout à fait. Vous pouvez réserver la consultation gratuite, et revenir quand vous voulez.'],
        ];

        $cases = [];
        foreach ($scenarios as $name => [$question, $answer]) {
            $cases[$name.' (écrit)'] = [$question, $answer, false];
            $cases[$name.' (appel)'] = [$question, $answer, true];
        }

        return $cases;
    }

    #[DataProvider('objectionScenarios')]
    public function test_common_objections_are_answered_without_sending_to_the_founder(string $question, string $answer, bool $call): void
    {
        $candidate = ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $answer]]], 'finishReason' => 'STOP']]];
        Http::fake(['generativelanguage.googleapis.com/*' => $call
            ? Http::response('data: '.json_encode($candidate)."\r\n\r\n", 200, ['Content-Type' => 'text/event-stream'])
            : Http::response($candidate)]);

        $request = $this->withCredentials()->withCookie('ama_visitor', self::VISITOR);

        if ($call) {
            $content = $request->post(route('assistant.call'), ['message' => $question])->assertOk()->streamedContent();
            $this->assertStringContainsString('"handoff":null', $content);
            $this->assertStringNotContainsString('transmettre', $content);
        } else {
            $response = $request->postJson(route('assistant.message'), ['message' => $question])->assertOk();
            $this->assertSame($answer, $response->json('reply'));
            $this->assertNull($response->json('handoff'));
        }

        $this->assertFalse(AssistantMessage::query()->where('role', AssistantMessage::AMA)->sole()->unanswered);

        Http::assertSent(function (Request $sent): bool {
            $system = (string) data_get($sent->data(), 'system_instruction.parts.0.text');

            return str_contains($system, 'Règle « Ton »')
                && str_contains($system, 'Règle « Objections »')
                && str_contains($system, 'Une objection n\'est jamais une question sans réponse');
        });
    }

    /**
     * Contrainte de Joseph : au plus environ 600 jetons ajoutés aux règles (ton + compléments des objections), et
     * moins de 10 000 jetons par demande habituelle (à l'écrit comme en appel, avec la conversation).
     */
    public function test_the_new_rules_stay_light(): void
    {
        $this->seed(FormationSeeder::class);

        $rules = AmaKnowledge::tokens(AmaPrompt::toneRules().AmaPrompt::objectionRules());
        $this->assertLessThanOrEqual(self::OBJECTION_RULES_BEFORE + 600, $rules);

        $this->assertLessThan(10000, AmaPrompt::stats()['perRequest']);
        $this->assertLessThan(10000, AmaKnowledge::tokens(AmaPrompt::build([], call: true)) + AmaPrompt::CONVERSATION_ALLOWANCE);
    }
}
