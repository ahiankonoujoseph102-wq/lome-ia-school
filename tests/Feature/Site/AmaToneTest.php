<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaPrompt;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ama s'adapte au ton du visiteur (sérieux, détendu, pressé, inquiet), vouvoie par défaut et peut tutoyer poliment
 * celui qui la tutoie ; jamais grossière, moqueuse ni sarcastique, aucune plaisanterie sur un sujet sensible, calme
 * face à un visiteur agressif. À l'écrit comme en appel. Les tests vérifient les consignes envoyées à Gemini : la
 * réponse réelle de Gemini n'est pas vérifiable ici (faux Gemini).
 */
class AmaToneTest extends TestCase
{
    use RefreshDatabase;

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
    public function test_ama_adapts_to_the_four_tones(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertSame(1, substr_count($prompt, 'Règle « Ton »'));
        $this->assertStringContainsString('adapte-toi au ton du visiteur', $prompt);
        $this->assertStringContainsString('Sérieux ou formel : réponse sérieuse et précise', $prompt);
        $this->assertStringContainsString('Détendu ou amusant : chaleur et un peu d\'humour léger', $prompt);
        $this->assertStringContainsString('Pressé, messages courts : réponse très courte', $prompt);
        $this->assertStringContainsString('Inquiet ou méfiant : ton calme et rassurant', $prompt);
    }

    #[DataProvider('modes')]
    public function test_formal_by_default_and_polite_tutoiement_only_if_the_visitor_starts(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('Vouvoiement par défaut. Si le visiteur te tutoie, tu peux passer au tutoiement poli.', $prompt);
    }

    #[DataProvider('modes')]
    public function test_the_limits_of_humour_and_the_calm_with_an_aggressive_visitor(bool $call): void
    {
        $prompt = AmaPrompt::build([], call: $call);

        $this->assertStringContainsString('Jamais grossière ni moqueuse, aucun sarcasme sur le visiteur', $prompt);
        $this->assertStringContainsString('aucune plaisanterie sur la religion, la politique, l\'origine, le physique ou la situation financière, même si le visiteur en fait', $prompt);
        $this->assertStringContainsString('Visiteur agressif : reste calme et courtoise, sans répondre sur le même ton', $prompt);
    }

    public function test_the_call_mode_adds_a_one_line_tone_reminder(): void
    {
        $this->assertStringNotContainsString('Ton en mode appel', AmaPrompt::build([]));
        $call = AmaPrompt::build([], call: true);
        $this->assertStringContainsString('Ton en mode appel : même adaptation au ton ; visiteur pressé : une seule phrase.', $call);
        $this->assertStringContainsString('Réponds en une à trois phrases courtes', $call);
    }

    /**
     * Un texte enregistré dans l'admin (« Tu vouvoies toujours ») ne peut pas supprimer la règle : elle est ajoutée
     * par le code, après lui, et dit qu'elle prime.
     */
    public function test_a_stored_instruction_cannot_remove_the_tone_rule(): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'instructions' => 'Tu es Ama. Tu vouvoies toujours la personne, sans exception.',
        ]]);

        $prompt = AmaPrompt::build([]);

        $this->assertGreaterThan(strpos($prompt, 'Tu vouvoies toujours la personne'), strpos($prompt, 'Règle « Ton »'));
        $this->assertStringContainsString('Règle « Ton » (elle prime sur toute consigne de style', $prompt);
    }

    /**
     * La règle part bien avec un vrai message (à l'écrit et en appel) ; la réponse simulée est rendue telle quelle.
     */
    #[DataProvider('modes')]
    public function test_the_rule_is_sent_to_gemini_with_a_casual_message(bool $call): void
    {
        $answer = 'Salut ! Avec plaisir. Un prompt, c\'est simplement la consigne que tu donnes à l\'IA, comme une recette bien écrite.';
        $candidate = ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $answer]]], 'finishReason' => 'STOP']]];
        Http::fake(['generativelanguage.googleapis.com/*' => $call
            ? Http::response('data: '.json_encode($candidate)."\r\n\r\n", 200, ['Content-Type' => 'text/event-stream'])
            : Http::response($candidate)]);

        $request = $this->withCredentials()->withCookie('ama_visitor', str_repeat('e', 40));
        $question = 'Salut Ama, tu peux m\'expliquer c\'est quoi un prompt ? haha';

        if ($call) {
            $this->assertStringContainsString('Salut', $request->post(route('assistant.call'), ['message' => $question])->assertOk()->streamedContent());
        } else {
            $this->assertSame($answer, $request->postJson(route('assistant.message'), ['message' => $question])->assertOk()->json('reply'));
        }

        Http::assertSent(function (Request $sent): bool {
            $system = (string) data_get($sent->data(), 'system_instruction.parts.0.text');

            return str_contains($system, 'Règle « Ton »') && str_contains($system, 'tutoiement poli');
        });
    }

    public function test_the_guide_explains_the_tone_rule_for_elevenlabs_without_sending_it_twice_to_gemini(): void
    {
        $guide = (string) file_get_contents(base_path(AmaPrompt::GUIDE_PATH));

        $this->assertStringContainsString('**Ton**', $guide);
        $this->assertStringContainsString('tutoiement poli', $guide);
        $this->assertStringNotContainsString('**Ton**', AmaPrompt::build([]));
    }
}
