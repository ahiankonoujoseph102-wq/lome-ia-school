<?php

namespace Tests\Feature\Site;

use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\VoiceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Ama dit « Bonjour » avant midi et « Bonsoir » à partir de midi (heure du téléphone du visiteur), avec le prénom
 * si la personne est connectée, dans la bulle de présentation et le premier message d'une conversation.
 * Calculé dans le navigateur : aucun appel à Gemini, et le prénom n'est jamais envoyé au serveur ni à Google.
 */
class AmaGreetingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function run_(string $scenario): array
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama.js.');
        }

        $ama = json_encode('file://'.resource_path('js/ama.js'));
        $program = <<<JS
            const { personalGreeting, greetingWord, cleanFirstName } = await import({$ama});
            const out = {};
            {$scenario}
            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();

        return json_decode(trim($process->getOutput()), true);
    }

    public function test_bonjour_before_noon_and_bonsoir_from_noon(): void
    {
        $out = $this->run_(<<<'JS'
            out.words = Array.from({ length: 24 }, (_, hour) => greetingWord(hour));
            JS);

        $this->assertSame(array_merge(array_fill(0, 12, 'Bonjour'), array_fill(0, 12, 'Bonsoir')), $out['words']);
    }

    public function test_the_bubble_and_the_first_message_greet_with_the_first_name(): void
    {
        $out = $this->run_(<<<'JS'
            const welcome = "Bonjour, je suis Ama, l'assistante de Lomé IA School. Comment puis-je vous aider ?";
            out.morningGuest = personalGreeting(welcome, { hour: 9 });
            out.eveningMember = personalGreeting(welcome, { hour: 19, firstName: 'Kossi Agbeko' });
            out.noon = personalGreeting(welcome, { hour: 12, firstName: 'kossi' });
            out.nudge = personalGreeting('Une question sur cette formation ? Posez-la-moi, je vous réponds.', { hour: 20, firstName: 'Afi' });
            out.nudgeMorning = personalGreeting('Envie de vous inscrire ? Je peux vous aider à le faire.', { hour: 7 });
            out.exclaim = personalGreeting('Bonjour ! Je suis Ama.', { hour: 15, firstName: 'Ama' });
            out.call = personalGreeting("Bonjour, je suis Ama, l'assistante de Lomé IA School. Je vous écoute.", { hour: 21, firstName: 'Jean-Paul' });
            out.difficulty = personalGreeting('Je vois que votre code promo a été refusé.', { hour: 10, firstName: 'Kossi' });
            out.names = ['  ', '<b>Kossi</b>', "N'Guessan Koffi", '42', 'émilie'].map(cleanFirstName);
            JS);

        $this->assertSame('Bonjour, je suis Ama, l\'assistante de Lomé IA School. Comment puis-je vous aider ?', $out['morningGuest']);
        $this->assertSame('Bonsoir Kossi, je suis Ama, l\'assistante de Lomé IA School. Comment puis-je vous aider ?', $out['eveningMember']);
        $this->assertSame('Bonsoir Kossi, je suis Ama, l\'assistante de Lomé IA School. Comment puis-je vous aider ?', $out['noon']);
        $this->assertSame('Bonsoir Afi ! Une question sur cette formation ? Posez-la-moi, je vous réponds.', $out['nudge']);
        $this->assertSame('Bonjour ! Envie de vous inscrire ? Je peux vous aider à le faire.', $out['nudgeMorning']);
        $this->assertSame('Bonsoir Ama ! Je suis Ama.', $out['exclaim']);
        $this->assertSame('Bonsoir Jean-Paul, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.', $out['call']);
        $this->assertSame('Bonjour Kossi ! Je vois que votre code promo a été refusé.', $out['difficulty']);
        $this->assertSame(['', 'BKossib', 'N\'Guessan', '', 'Émilie'], $out['names']);
    }

    /**
     * Réglages d'Ama tels que la page les donne au navigateur.
     *
     * @return array<string, mixed>
     */
    private function amaConfig(string $html): array
    {
        $this->assertSame(1, preg_match('/voiceAssistant\(JSON\.parse\(\'(.*?)\'\)\)/s', $html, $match));

        return json_decode((string) json_decode('"'.$match[1].'"'), true);
    }

    private function enableAma(): void
    {
        SiteSettings::save(['site.voice_assistant' => [...(array) config('site.voice_assistant'), 'enabled' => true, 'provider' => 'gemini']]);
        config(['services.gemini.key' => 'cle-de-test']);
    }

    public function test_only_a_connected_person_gets_a_first_name_and_it_never_goes_into_the_context(): void
    {
        $this->enableAma();

        $this->assertSame('', $this->amaConfig((string) $this->get(route('home'))->getContent())['firstName']);

        $user = User::factory()->create(['name' => 'kossi Agbeko']);
        $config = $this->amaConfig((string) $this->actingAs($user)->get(route('home'))->getContent());

        $this->assertSame('Kossi', $config['firstName']);
        $this->assertStringNotContainsStringIgnoringCase('Kossi', (string) json_encode($config['context']));
        $this->assertSame('', VoiceAssistant::firstName(null));
    }

    public function test_the_greeting_is_written_by_the_browser_without_any_call_to_gemini(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->enableAma();
        $user = User::factory()->create(['name' => 'Kossi Agbeko']);

        $config = $this->amaConfig((string) $this->actingAs($user)->get(route('formations.index'))->assertOk()->getContent());
        $this->assertSame('Kossi', $config['firstName']);
        $this->assertNotEmpty($config['nudge']['text']);
        Http::assertNothingSent();

        // Le message envoyé au serveur ne contient que la question, la conversation et le contexte (jamais le prénom).
        $js = (string) file_get_contents(resource_path('js/site.js'));
        $this->assertStringContainsString('body: JSON.stringify({ message, conversation: this.conversation, context: pick(this.context), voice: voice || this.usedVoice })', $js);
        $this->assertStringContainsString("this.nudgeText = this.greet(config.nudge?.text ?? '');", $js);
        $this->assertStringContainsString('text: this.greet(greeting)', $js);
    }
}
