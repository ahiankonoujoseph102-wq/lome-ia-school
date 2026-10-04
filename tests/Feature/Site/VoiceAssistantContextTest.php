<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use App\Models\PromoCode;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\VoiceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Assistante Ama : contexte transmis sans aucune donnée personnelle, repérage des difficultés
 * (bulle au maximum une fois par page et par visite) et jamais d'appel ni de micro ouverts tout seuls.
 */
class VoiceAssistantContextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Attributs lus par le widget officiel, relevés dans le code de la version 0.18.3 (liste « Fv » de dist/index.js).
     *
     * @var list<string>
     */
    private const array WIDGET_ATTRIBUTES = ['variant', 'placement', 'override-config', 'avatar-image-url', 'avatar-orb-color-1', 'avatar-orb-color-2', 'agent-id', 'signed-url', 'orchestrator-url', 'orchestrator-agent-config', 'terms-key', 'server-location', 'language', 'dynamic-variables', 'show-avatar-when-collapsed', 'override-prompt', 'override-llm', 'override-speed', 'override-stability', 'override-similarity-boost', 'override-first-message', 'override-language', 'override-voice-id', 'override-text-only', 'mic-muting', 'transcript', 'text-input', 'text-contents', 'default-expanded', 'always-expanded', 'dismissible', 'strip-audio-tags', 'user-id', 'use-rtc', 'collect-feedback', 'worklet-path-raw-audio-processor', 'worklet-path-audio-concat-processor', 'worklet-path-libsamplerate', 'syntax-highlight-theme', 'markdown-link-allowed-hosts', 'markdown-link-include-www', 'markdown-link-allow-http', 'allow-events', 'show-agent-status', 'show-conversation-id', 'show-resize-button', 'show-language-selector-on-trigger', 'environment'];

    protected function setUp(): void
    {
        parent::setUp();

        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'elevenlabs', 'agent_id' => 'agent_9901m3phrpdwedt88vsdqmnwk09z', 'proactive' => true, 'help_delay' => 90]]);
    }

    private function student(): User
    {
        return User::factory()->create(['name' => 'Afi Mensah', 'email' => 'afi.mensah@example.com', 'phone' => '+22890123456']);
    }

    /**
     * Réglages envoyés au composant du bouton, lus dans la page (x-data="voiceAssistant(JSON.parse('…'))").
     *
     * @return array<string, mixed>
     */
    private function config(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $this->assertSame(1, preg_match('/x-data="voiceAssistant\(JSON\.parse\(\'(.*?)\'\)\)"/s', $html, $match), 'Composant de l\'assistante absent.');
        $json = json_decode('"'.html_entity_decode($match[1], ENT_QUOTES).'"');

        return json_decode((string) $json, true);
    }

    private function assertNoPersonalData(array $config, User $user): void
    {
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE);

        foreach (['Afi', 'Mensah', 'afi.mensah', '@example.com', '90123456', '22890123456', 'password'] as $personal) {
            $this->assertStringNotContainsStringIgnoringCase($personal, (string) $encoded);
        }

        $this->assertSame(['page', 'role_page', 'formation', 'prix_formation', 'montant_a_payer', 'connecte', 'email_confirme', 'erreur', 'difficulte'], array_keys($config['context']));
    }

    public function test_the_checkout_context_describes_the_page_formation_and_amount_without_personal_data(): void
    {
        $user = $this->student();
        $formation = Formation::factory()->create(['title' => 'Automatisation avec l\'IA', 'price' => 25500]);

        $config = $this->config($this->actingAs($user)->get(route('checkout.show', $formation))->assertOk());

        $this->assertNoPersonalData($config, $user);
        $this->assertSame('page de commande et de paiement', $config['context']['page']);
        $this->assertSame('Automatisation avec l\'IA', $config['context']['formation']);
        $this->assertSame("25\u{00A0}500\u{00A0}FCFA", $config['context']['prix_formation']);
        $this->assertSame("25\u{00A0}500\u{00A0}FCFA", $config['context']['montant_a_payer']);
        $this->assertTrue($config['context']['connecte']);
        $this->assertTrue($config['context']['email_confirme']);
        $this->assertNull($config['context']['erreur']);
        $this->assertSame('checkout.show', $config['pageKey']);
        $this->assertTrue($config['proactive']);
        $this->assertSame(90, $config['helpDelay']);
    }

    public function test_student_pages_never_send_the_name_email_or_phone(): void
    {
        $user = $this->student();

        foreach (['dashboard', 'student.profile', 'student.purchases.index', 'student.notifications', 'student.affiliate.index'] as $route) {
            $config = $this->config($this->actingAs($user)->get(route($route))->assertOk());
            $this->assertNoPersonalData($config, $user);
            $this->assertTrue($config['context']['connecte']);
        }

        auth()->logout();
        $config = $this->config($this->get(route('login'))->assertOk());
        $this->assertFalse($config['context']['connecte']);
        $this->assertNull($config['context']['email_confirme']);
    }

    public function test_a_refused_promo_code_is_a_difficulty_and_the_amount_includes_a_valid_code(): void
    {
        $user = $this->student();
        $formation = Formation::factory()->create(['price' => 60000]);

        $config = $this->config($this->actingAs($user)->get(route('checkout.show', [$formation, 'code' => 'INVENTE']))->assertOk());
        $this->assertSame('promo_refuse', $config['context']['difficulte']);
        $this->assertSame('promo_code', $config['context']['erreur']['cle']);
        $this->assertNotSame('', $config['context']['erreur']['texte']);
        $this->assertNoPersonalData($config, $user);

        PromoCode::query()->create(['code' => 'RENTREE', 'type' => 'percent', 'value' => 50]);
        $config = $this->config($this->actingAs($user)->get(route('checkout.show', [$formation, 'code' => 'RENTREE']))->assertOk());
        $this->assertSame("30\u{00A0}000\u{00A0}FCFA", $config['context']['montant_a_payer']);
        $this->assertNull($config['context']['difficulte']);
    }

    public function test_a_refused_capture_is_a_difficulty(): void
    {
        $user = $this->student();
        $formation = Formation::factory()->create(['price' => 5500]);

        $response = $this->actingAs($user)->from(route('checkout.show', $formation))->followingRedirects()
            ->post(route('checkout.pay', $formation), ['payment_method' => 'tg-mixx'])
            ->assertOk()
            ->assertSee('Ajoutez la capture d&#039;écran de votre paiement.', false);

        $config = $this->config($response);
        $this->assertSame('capture_echec', $config['context']['difficulte']);
        $this->assertSame('proof', $config['context']['erreur']['cle']);
    }

    public function test_a_wrong_email_code_is_passed_without_the_code_itself(): void
    {
        $user = User::factory()->create(['email_verified_at' => null, 'email' => 'kossi@example.com']);

        $response = $this->actingAs($user)->from(route('email-confirmation.show'))->followingRedirects()
            ->post(route('email-confirmation.confirm'), ['code' => '483920'])
            ->assertOk();

        $config = $this->config($response);
        $this->assertSame('code', $config['context']['erreur']['cle']);
        $this->assertStringContainsString('code', $config['context']['erreur']['texte']);
        $this->assertFalse($config['context']['email_confirme']);
        $this->assertStringNotContainsString('483920', (string) json_encode($config));
        $this->assertStringNotContainsString('kossi', (string) json_encode($config));
    }

    public function test_the_server_cleans_error_texts(): void
    {
        $this->assertSame('Écrivez à [adresse e-mail] ou au [nombre].', VoiceAssistant::clean('Écrivez à afi@example.com ou au +228 90 12 34 56.'));
        $this->assertSame('Code [nombre] refusé, voir [lien]', VoiceAssistant::clean('Code 483920 refusé, voir https://site.test/confirmer-email/abc'));
        $this->assertSame('Vérifiez les 6 chiffres (2 essais restants).', VoiceAssistant::clean('Vérifiez les 6 chiffres (2 essais restants).'));
        $this->assertSame(200, mb_strlen(VoiceAssistant::clean(str_repeat('a ', 300))));
    }

    public function test_the_help_bubble_is_opt_out_and_its_delay_is_set_in_the_admin(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'elevenlabs', 'agent_id' => 'agent_9901m3phrpdwedt88vsdqmnwk09z', 'proactive' => false, 'help_delay' => 45]]);

        $config = $this->config($this->get(route('home'))->assertOk());
        $this->assertFalse($config['proactive']);
        $this->assertSame(45, $config['helpDelay']);
        $this->get(route('home'))->assertSee('Vous semblez bloqué ?');
    }

    public function test_the_widget_only_uses_official_attributes_and_never_starts_the_call_or_the_microphone(): void
    {
        $script = (string) file_get_contents(resource_path('js/site.js'));
        $component = substr($script, (int) strpos($script, "Alpine.data('voiceAssistant'"));
        $component = substr($component, 0, (int) strpos($component, 'const startAlpine'));

        preg_match_all("/widget\\.setAttribute\\('([a-z-]+)'/", $component, $matches);
        $attributes = array_values(array_unique($matches[1]));
        sort($attributes);
        $this->assertSame(['agent-id', 'allow-events', 'default-expanded', 'dynamic-variables', 'placement', 'text-contents'], $attributes);
        $this->assertSame([], array_diff($matches[1], self::WIDGET_ATTRIBUTES));

        // Rien ne démarre l'appel ni le micro : c'est le visiteur qui touche « Démarrer l'appel » dans le widget.
        foreach (['getUserMedia', 'startSession', 'elevenlabs-agent:user-message', 'always-expanded'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $component);
            $this->assertStringNotContainsString($forbidden, (string) file_get_contents(resource_path('js/ama.js')));
        }

        // Le panneau ne s'ouvre que par un toucher : bouton « Parler avec notre assistante », bulle d'aide ou bulle de
        // présentation d'Ama.
        $partial = (string) file_get_contents(resource_path('views/site/partials/voice-assistant.blade.php'));
        $this->assertSame(3, substr_count($partial, '@click="open()"'));
        $this->assertStringNotContainsString('x-init', $partial);
        // Le composant ne s'ouvre jamais lui-même (ni à l'arrivée d'une difficulté, ni après un délai).
        $this->assertStringNotContainsString('this.open(', $component);
    }

    /**
     * Logique du navigateur (resources/js/ama.js) exécutée avec Node.js.
     */
    public function test_browser_logic_sends_no_personal_data_and_offers_the_bubble_once_per_page(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama.js.');
        }

        $module = json_encode('file://'.resource_path('js/ama.js'));
        $difficulties = json_encode(VoiceAssistant::DIFFICULTIES, JSON_UNESCAPED_UNICODE);
        $program = <<<JS
            const { buildVariables, clean, createHelpTracker, pick } = await import({$module});
            const memory = () => { const data = {}; return { getItem: (k) => data[k] ?? null, setItem: (k, v) => { data[k] = String(v); } }; };
            const out = {};

            // Champs inconnus ignorés (nom, e-mail, téléphone), erreurs nettoyées.
            out.picked = pick({ page: 'page de commande', nom: 'Afi Mensah', email: 'afi@example.com', telephone: '+22890123456', erreur: { cle: 'x', texte: 'Écrivez à afi@example.com ou au +228 90 12 34 56' } });
            out.clean = clean('Code 483920 envoyé à kossi@example.com');

            const context = { page: 'page de commande et de paiement', role_page: 'paiement en 4 étapes', etape: 'étape 3 sur 4 : envoyer l\'argent', formation: 'Formation Exemple', prix_formation: '25 000 FCFA', montant_a_payer: '25 000 FCFA', pays: 'Togo', operateur: 'Mixx by Yas', connecte: true, email_confirme: true, erreur: null, difficulte: 'promo_refuse' };
            out.variables = buildVariables(context, { difficulties: {$difficulties}, greeting: 'Bonjour' });
            out.plain = buildVariables({ page: 'page d\'accueil', connecte: false }, { difficulties: {$difficulties}, greeting: 'Bonjour' });

            // Bulle : une fois par page et par visite, même avec un nouveau suivi (page rechargée) ; autre page : de nouveau possible.
            const storage = memory();
            const first = createHelpTracker({ storage, pageKey: 'checkout.show' });
            const again = createHelpTracker({ storage, pageKey: 'checkout.show' });
            const other = createHelpTracker({ storage, pageKey: 'login' });
            const off = createHelpTracker({ storage: memory(), pageKey: 'checkout.show', enabled: false });
            out.offers = [first.offer(), first.offer(), again.offer(), other.offer(), off.offer()];

            // Erreurs : code promo et capture tout de suite ; même erreur et code e-mail faux à la deuxième fois.
            const errors = createHelpTracker({ storage: memory(), pageKey: 'register' });
            out.errors = [
                errors.errorShown({ cle: 'promo_code', texte: 'Code inconnu' }),
                errors.errorShown({ cle: 'proof', texte: 'Image trop lourde' }),
                errors.errorShown({ cle: 'email', texte: 'Adresse déjà utilisée' }),
                errors.errorShown({ cle: 'email', texte: 'Adresse déjà utilisée' }),
            ];
            const codes = createHelpTracker({ storage: memory(), pageKey: 'email-confirmation.show' });
            out.codes = [codes.errorShown({ cle: 'code', texte: 'Code incorrect (4 essais restants).' }), codes.errorShown({ cle: 'code', texte: 'Code incorrect (3 essais restants).' })];

            // Allers-retours : au deuxième retour en arrière.
            const steps = createHelpTracker({ storage: memory(), pageKey: 'checkout.show' });
            out.steps = [1, 2, 3, 2, 3, 2].map((step) => steps.stepChanged(step));

            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame(['page', 'role_page', 'etape', 'formation', 'prix_formation', 'montant_a_payer', 'pays', 'operateur', 'connecte', 'email_confirme', 'erreur', 'difficulte'], array_keys($out['picked']));
        $this->assertSame('Écrivez à [adresse e-mail] ou au [nombre]', $out['picked']['erreur']['texte']);
        $this->assertSame('Code [nombre] envoyé à [adresse e-mail]', $out['clean']);

        foreach ([$out['variables'], $out['plain']] as $variables) {
            $this->assertSame(['contexte', 'message_accueil', 'page', 'etape', 'formation', 'prix_formation', 'montant_a_payer', 'pays', 'operateur', 'connecte', 'email_confirme', 'derniere_erreur', 'difficulte'], array_keys($variables));
            $this->assertContainsOnly('string', $variables);
        }

        $this->assertStringContainsString('Étape : étape 3 sur 4', $out['variables']['contexte']);
        // Les montants gardent leurs chiffres (seules les erreurs affichées sont nettoyées).
        $this->assertStringContainsString('Montant à payer : 25 000 FCFA.', $out['variables']['contexte']);
        $this->assertSame('25 000 FCFA', $out['variables']['montant_a_payer']);
        $this->assertStringContainsString('Opérateur choisi : Mixx by Yas.', $out['variables']['contexte']);
        $this->assertStringContainsString('Difficulté repérée par le site : Le code promo saisi a été refusé.', $out['variables']['contexte']);
        $this->assertSame(VoiceAssistant::DIFFICULTIES['promo_refuse'][1], $out['variables']['message_accueil']);
        $this->assertSame('Bonjour', $out['plain']['message_accueil']);
        $this->assertSame('non', $out['plain']['connecte']);
        $this->assertSame('sans objet', $out['plain']['email_confirme']);

        $this->assertSame([true, false, false, true, false], $out['offers']);
        $this->assertSame(['promo_refuse', 'capture_echec', null, 'meme_erreur'], $out['errors']);
        $this->assertSame([null, 'code_email_faux'], $out['codes']);
        $this->assertSame([null, null, null, null, null, 'allers_retours'], $out['steps']);
    }
}
