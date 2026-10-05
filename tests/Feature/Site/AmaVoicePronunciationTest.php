<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaPrompt;
use App\Assistant\NaturalVoice;
use App\Assistant\VoicePronunciation;
use App\Jobs\PrepareAmaVoicePhrases;
use App\Models\AdminActivity;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Table de prononciation de la voix naturelle d'Ama : appliquée seulement au texte envoyé au fournisseur de voix
 * (Cloudflare et Google), mots entiers, sigles en respectant les majuscules, expressions les plus longues d'abord, table
 * modifiable dans l'admin, « Phrase d'essai » avec la table en cours de saisie, phrases en mémoire refaites quand la table
 * change. Faux Cloudflare et faux Google (Http::fake) : aucun vrai appel.
 */
class AmaVoicePronunciationTest extends TestCase
{
    use RefreshDatabase;

    private const string VISITOR = 'pppppppppppppppppppppppppppppppppppppppp';

    private string $geminiReply = '';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $mp3 = str_repeat("\xFF\xFB\x10\x64".str_repeat("\x00", 100), 40);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['result' => ['audio' => base64_encode($mp3)], 'success' => true, 'errors' => [], 'messages' => []]),
            // Modèle de texte (réponses d'Ama) ou modèle de voix.
            'generativelanguage.googleapis.com/*' => fn (Request $request) => str_contains($request->url(), 'modele-texte-de-test')
                ? Http::response(['candidates' => [['content' => ['parts' => [['text' => $this->geminiReply]]], 'finishReason' => 'STOP']]])
                : Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/wav', 'data' => base64_encode('RIFF'.str_repeat("\x00", 40))]]]]]]]),
        ]);
        Storage::fake('ama_voice');
        config([
            'services.gemini.key' => 'cle-gemini-de-test',
            'services.gemini.model' => 'modele-texte-de-test',
            'services.cloudflare.account_id' => 'compte-de-test',
            'services.cloudflare.api_token' => 'jeton-de-test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $natural
     */
    private function enable(string $provider = 'cloudflare', array $natural = []): void
    {
        SiteSettings::save(['site.voice_assistant' => [
            'enabled' => true,
            'provider' => 'gemini',
            'natural_voice' => ['enabled' => true, 'provider' => $provider, 'voice' => 'Kore', 'model' => 'gemini-3.8-flash-lite-tts', 'minute_limit' => 60, 'cloudflare_minute_limit' => 60, ...$natural],
        ]]);
    }

    private function voice(string $text): TestResponse
    {
        return $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)->postJson(route('assistant.voice'), ['text' => $text]);
    }

    /**
     * Textes reçus par le faux fournisseur (Cloudflare : « prompt » ; Google : la partie texte).
     *
     * @return list<string>
     */
    private function sentTexts(): array
    {
        return Http::recorded()->map(fn (array $pair): string => (string) ($pair[0]['prompt'] ?? $pair[0]['contents'][0]['parts'][0]['text'] ?? ''))->values()->all();
    }

    public function test_the_default_table_matches_the_request(): void
    {
        $this->assertSame([
            ['written' => 'IA', 'spoken' => 'i a'],
            ['written' => 'Lomé IA School', 'spoken' => 'Lomé i a Scoul'],
            ['written' => 'ChatGPT', 'spoken' => 'Tchate G P T'],
            ['written' => 'SaaS', 'spoken' => 'sasse'],
            ['written' => 'FCFA', 'spoken' => 'francs C F A'],
        ], VoicePronunciation::table());
    }

    public function test_only_whole_words_are_replaced_never_inside_a_word_like_confiance(): void
    {
        $this->assertSame('Faites confiance à l\'i a.', VoicePronunciation::apply('Faites confiance à l\'IA.'));
        $this->assertSame('confiance, Asia, sociale, IAS, MIA', VoicePronunciation::apply('confiance, Asia, sociale, IAS, MIA'));
        $this->assertSame('Elle coûte 25 000 francs C F A.', VoicePronunciation::apply('Elle coûte 25 000 FCFA.'));
        $this->assertSame('Avec Tchate G P T et un sasse.', VoicePronunciation::apply('Avec ChatGPT et un SaaS.'));
        // Ponctuation et apostrophes autour : toujours un mot entier.
        $this->assertSame('(i a), « i a » et i a-générative', VoicePronunciation::apply('(IA), « IA » et IA-générative'));
    }

    public function test_acronyms_respect_capital_letters(): void
    {
        // « ia », « Ia » : pas le sigle, jamais touchés.
        $this->assertSame('ia Ia ChatGpt chatgpt fcfa', VoicePronunciation::apply('ia Ia ChatGpt chatgpt fcfa'));
        $this->assertTrue(VoicePronunciation::isCaseSensitive('IA'));
        $this->assertTrue(VoicePronunciation::isCaseSensitive('SaaS'));
        $this->assertFalse(VoicePronunciation::isCaseSensitive('Gemini'));

        // Mot ordinaire (une seule majuscule ou aucune) : remplacé quelle que soit la casse.
        $table = [['written' => 'Gemini', 'spoken' => 'Jémini']];
        $this->assertSame('Jémini, Jémini et Jémini', VoicePronunciation::apply('Gemini, gemini et GEMINI', $table));
    }

    public function test_the_longest_expressions_are_replaced_first_and_only_once(): void
    {
        // « Lomé IA School » passe avant « IA », quel que soit l'ordre de la table.
        $this->assertSame('Bienvenue chez Lomé i a Scoul, l\'école d\'i a.', VoicePronunciation::apply('Bienvenue chez Lomé IA School, l\'école d\'IA.'));

        $reversed = array_reverse(VoicePronunciation::DEFAULTS);
        $this->assertSame('Lomé i a Scoul', VoicePronunciation::apply('Lomé IA School', $reversed));

        // Un seul passage : le texte remplacé n'est jamais remplacé à nouveau.
        $chain = [['written' => 'A', 'spoken' => 'B'], ['written' => 'B', 'spoken' => 'C']];
        $this->assertSame('B C', VoicePronunciation::apply('A B', $chain));

        // Espace insécable dans le texte : l'expression est quand même reconnue.
        $this->assertSame('Lomé i a Scoul', VoicePronunciation::apply("Lomé\u{00A0}IA School"));
    }

    public function test_the_table_is_cleaned_and_an_emptied_table_stays_empty(): void
    {
        $this->assertSame(
            [['written' => 'Lomé IA School', 'spoken' => 'Lomé i a Scoul']],
            VoicePronunciation::clean([
                ['written' => "  Lomé \u{00A0} IA   School ", 'spoken' => ' Lomé i a Scoul '],
                ['written' => 'Lomé IA School', 'spoken' => 'en double'],
                ['written' => 'Seul', 'spoken' => ''],
                ['written' => '', 'spoken' => 'rien'],
                ['written' => '...', 'spoken' => 'points'],
                'pas une ligne',
            ]),
        );

        SiteSettings::save(['site.voice_assistant' => ['natural_voice' => ['pronunciations' => []]]]);
        $this->assertSame([], VoicePronunciation::table());
        $this->assertSame('Lomé IA School', VoicePronunciation::apply('Lomé IA School'));
    }

    public function test_cloudflare_receives_the_pronounced_text_and_the_visitor_gets_the_audio(): void
    {
        $this->enable('cloudflare');

        $this->voice('La formation sur l\'IA coûte 25 000 FCFA, et la confiance compte.')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');

        $this->assertSame(['La formation sur l\'i a coûte 25 000 francs C F A, et la confiance compte.'], $this->sentTexts());
    }

    public function test_google_receives_the_pronounced_text_too(): void
    {
        $this->enable('gemini');

        $this->voice('Lomé IA School vous apprend ChatGPT.')->assertOk()->assertHeader('Content-Type', 'audio/wav');

        $this->assertSame(['Lomé i a Scoul vous apprend Tchate G P T.'], $this->sentTexts());
    }

    public function test_the_text_shown_by_ama_never_changes(): void
    {
        $this->enable('cloudflare');
        $this->geminiReply = 'L\'IA de Lomé IA School coûte 5 100 FCFA.';

        $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)
            ->postJson(route('assistant.message'), ['message' => 'Combien coûte la formation ?', 'context' => []])
            ->assertOk()
            ->assertJsonPath('reply', 'L\'IA de Lomé IA School coûte 5 100 FCFA.');

        // La configuration du navigateur ne contient pas la table : la voix du téléphone lit le texte tel quel.
        $this->get(route('home'))->assertOk()->assertDontSee('Tchate G P T')->assertDontSee('francs C F A');
    }

    public function test_the_privacy_check_still_happens_on_the_written_text(): void
    {
        $this->enable('cloudflare');

        // Numéro dans la réponse : jamais envoyé, même si la table touche le reste de la phrase.
        $this->voice('Appelez le +228 90 00 00 00 pour l\'IA.')->assertOk()->assertExactJson(['fallback' => true]);
        $this->assertSame([], $this->sentTexts());
    }

    public function test_the_admin_edits_the_table_and_the_prepared_phrases_are_made_again(): void
    {
        $admin = User::factory()->admin()->create();
        $this->enable('cloudflare');
        $voice = app(NaturalVoice::class);

        // Phrases écrites à l'avance déjà en mémoire avec la table de départ.
        foreach (NaturalVoice::preparedTexts() as $text) {
            $voice->speak($text);
        }
        $this->assertSame([], $voice->missingPreparedTexts());
        $this->assertContains('Bonjour, je suis Ama, l\'assistante de Lomé i a Scoul. Je vous écoute.', $this->sentTexts());

        $page = $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))->assertOk()
            ->assertSee('Table de prononciation')
            ->assertSee('Phrase d\'essai', false)
            ->assertSee('data-test="natural-voice-pronunciations"', false);
        $this->assertStringContainsString('Lomé i a Scoul', (string) $page->getContent());

        Queue::fake();
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'provider' => 'gemini',
            'natural_voice' => '1',
            'natural_voice_provider' => 'cloudflare',
            'pronunciations_sent' => '1',
            'pronunciations' => [
                ['written' => 'IA', 'spoken' => 'i a'],
                ['written' => 'Lomé IA School', 'spoken' => 'Lomé I A Skoul'],
                ['written' => '', 'spoken' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([
            ['written' => 'IA', 'spoken' => 'i a'],
            ['written' => 'Lomé IA School', 'spoken' => 'Lomé I A Skoul'],
        ], VoicePronunciation::table());
        $this->assertStringContainsString('table de prononciation modifiée (2 lignes)', (string) AdminActivity::query()->latest('id')->value('description'));
        Queue::assertPushed(PrepareAmaVoicePhrases::class);

        // Les phrases qui contiennent « Lomé IA School » n'ont plus d'audio en mémoire (ancienne prononciation) ; les autres
        // restent servies par la mémoire.
        $missing = $voice->missingPreparedTexts();
        $this->assertNotEmpty($missing);
        foreach ($missing as $text) {
            $this->assertStringContainsString('Lomé IA School', $text);
        }
        $this->assertNotContains(AmaPrompt::MISHEARD_REPLY, $missing);

        // La préparation les fabrique de nouveau, avec la nouvelle prononciation.
        $before = count(Http::recorded());
        while (($left = $voice->prepareNext()) !== null && $left > 0) {
            // une phrase par tour, comme la tâche en arrière-plan
        }
        $this->assertSame([], $voice->missingPreparedTexts());
        $this->assertContains('Bonjour, je suis Ama, l\'assistante de Lomé I A Skoul. Je vous écoute.', $this->sentTexts());
        $this->assertGreaterThan($before, count(Http::recorded()));
    }

    public function test_a_form_opened_before_the_update_keeps_the_table(): void
    {
        $admin = User::factory()->admin()->create();
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', 'natural_voice' => ['pronunciations' => [['written' => 'Ama', 'spoken' => 'Amah']]]]]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'provider' => 'gemini'])->assertRedirect();

        $this->assertSame([['written' => 'Ama', 'spoken' => 'Amah']], VoicePronunciation::table());
    }

    public function test_the_test_sentence_is_read_with_the_table_being_typed_before_saving(): void
    {
        $admin = User::factory()->admin()->create();
        $this->enable('cloudflare');

        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), [
            'provider' => 'cloudflare',
            'text' => 'Bienvenue chez Lomé IA School, en confiance.',
            'pronunciations' => [['written' => 'Lomé IA School', 'spoken' => 'Lomé I A Skoul']],
        ])->assertOk()->assertHeader('Content-Type', 'audio/mpeg');

        $this->assertSame(['Bienvenue chez Lomé I A Skoul, en confiance.'], $this->sentTexts());
        // Rien n'est enregistré par un essai.
        $this->assertSame(VoicePronunciation::DEFAULTS, VoicePronunciation::table());

        // Même essai : servi par la mémoire. Prononciation changée : nouvelle demande.
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), [
            'provider' => 'cloudflare',
            'text' => 'Bienvenue chez Lomé IA School, en confiance.',
            'pronunciations' => [['written' => 'Lomé IA School', 'spoken' => 'Lomé I A Skoul']],
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), [
            'provider' => 'cloudflare',
            'text' => 'Bienvenue chez Lomé IA School, en confiance.',
            'pronunciations' => [['written' => 'Lomé IA School', 'spoken' => 'Lomé i a Scoul']],
        ])->assertOk();
        $this->assertSame(['Bienvenue chez Lomé I A Skoul, en confiance.', 'Bienvenue chez Lomé i a Scoul, en confiance.'], $this->sentTexts());

        // Sans phrase d'essai : la phrase d'exemple, avec la table enregistrée (aucune table envoyée).
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['provider' => 'cloudflare'])->assertOk();
        $this->assertSame(VoicePronunciation::apply(NaturalVoice::SAMPLE), $this->sentTexts()[2]);
        $this->assertStringContainsString('Lomé i a Scoul', $this->sentTexts()[2]);

        // Avec Google aussi.
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), [
            'provider' => 'gemini', 'voice' => 'Kore', 'model' => 'gemini-3.8-flash-lite-tts', 'text' => 'Payez en FCFA.',
            'pronunciations' => VoicePronunciation::DEFAULTS,
        ])->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->assertSame('Payez en francs C F A.', $this->sentTexts()[3]);

        // Phrase trop longue ou sans lettre : refusée avec un message clair.
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['provider' => 'cloudflare', 'text' => str_repeat('a', NaturalVoice::PREVIEW_MAX_LENGTH + 1)])
            ->assertStatus(422)->assertJsonValidationErrors('text');
        $this->actingAs($admin)->postJson(route('admin.settings.voice-preview'), ['provider' => 'cloudflare', 'text' => '?!'])
            ->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'phrase d\'essai'));
        $this->assertCount(4, Http::recorded());
    }
}
