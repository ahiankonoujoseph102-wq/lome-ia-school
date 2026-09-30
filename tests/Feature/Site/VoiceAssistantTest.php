<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Events\FormationAccessGranted;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\VoiceAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Assistante (mode agent ElevenLabs, gardé au choix dans l'admin) : interrupteur et identifiant dans l'admin,
 * bouton flottant sur les pages publiques choisies seulement, aucun script chargé tant qu'elle est désactivée.
 * Ama (Gemini, par défaut) : tests/Feature/Site/AmaGeminiTest.php.
 */
class VoiceAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const string AGENT = 'agent_9901m3phrpdwedt88vsdqmnwk09z';

    private function enable(bool $enabled = true, string $agentId = self::AGENT): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => $enabled, 'provider' => 'elevenlabs', 'agent_id' => $agentId]]);
    }

    private function assertWidgetAbsent(TestResponse $response): void
    {
        $response->assertOk()
            ->assertDontSee('data-test="voice-assistant"', false)
            ->assertDontSee('elevenlabs', false)
            ->assertDontSee('unpkg.com', false)
            ->assertDontSee(self::AGENT, false);
    }

    private function assertWidgetPresent(TestResponse $response): void
    {
        $response->assertOk()
            ->assertSee('data-test="voice-assistant"', false)
            ->assertSee('Parler avec notre assistante')
            ->assertSee(self::AGENT, false)
            // Le script d'ElevenLabs n'est jamais dans la page : il est chargé au toucher du bouton.
            ->assertDontSee('<script src="'.VoiceAssistant::SCRIPT_URL, false);
    }

    /**
     * @return array<string, string>
     */
    private function publicPages(): array
    {
        $formation = Formation::factory()->create();

        return [
            'accueil' => route('home'),
            'catalogue' => route('formations.index'),
            'formation' => route('formations.show', $formation),
            'entreprises' => route('business'),
            'contact' => route('contact'),
        ];
    }

    public function test_it_is_disabled_by_default_and_prefilled_with_the_agent_id(): void
    {
        $this->assertFalse(config('site.voice_assistant.enabled'));
        // Ama (Gemini) est l'assistante par défaut ; l'agent ElevenLabs reste disponible dans l'admin.
        $this->assertSame(VoiceAssistant::GEMINI, VoiceAssistant::provider());
        $this->assertSame(self::AGENT, config('site.voice_assistant.agent_id'));
        $this->assertFalse(VoiceAssistant::enabled());
    }

    public function test_nothing_is_loaded_when_the_switch_is_off(): void
    {
        foreach ($this->publicPages() as $url) {
            $this->assertWidgetAbsent($this->get($url));
        }

        $this->enable(false);

        foreach ($this->publicPages() as $url) {
            $this->assertWidgetAbsent($this->get($url));
        }
    }

    public function test_the_button_is_on_every_public_page_when_enabled(): void
    {
        $this->enable();

        foreach ($this->publicPages() as $page => $url) {
            $this->assertWidgetPresent($this->get($url));
        }

        // Libellés français transmis au widget.
        $this->get(route('home'))->assertSee('Raccrocher')->assertSee('Couper le micro');
    }

    public function test_it_stays_hidden_with_an_invalid_agent_id(): void
    {
        $this->enable(true, 'mauvais-identifiant');

        $response = $this->get(route('home'))->assertOk();
        $response->assertDontSee('data-test="voice-assistant"', false)->assertDontSee('unpkg.com', false);
    }

    public function test_it_is_absent_from_the_admin_the_group_conversation_and_by_default_the_course_player(): void
    {
        $this->enable();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->assertWidgetAbsent($this->actingAs($admin)->get(route('admin.index')));
        $this->assertWidgetAbsent($this->actingAs($admin)->get(route('admin.settings.edit', 'coordonnees')));
        $this->assertWidgetAbsent($this->actingAs($admin)->get(route('admin.payments.index')));

        $lesson = Lesson::factory()->preview()->create();
        $this->assertWidgetAbsent($this->actingAs($admin)->get(route('lessons.show', [$lesson->formation(), $lesson])));

        $student = User::factory()->create();
        $formation = Formation::factory()->create();
        FormationAccessGranted::dispatch($student->enroll($formation, EnrollmentSource::Purchase));
        $this->assertWidgetAbsent($this->actingAs($student)->get(route('chat.show', $formation)));
        $this->assertWidgetAbsent($this->actingAs($student)->get(route('lessons.show', [$lesson->formation(), $lesson])));

        auth()->logout();
        $this->assertWidgetAbsent($this->get(route('lessons.show', [$lesson->formation(), $lesson])));
    }

    public function test_the_course_player_shows_it_only_when_the_admin_chooses_it(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'elevenlabs', 'agent_id' => self::AGENT, 'lessons' => true]]);
        $lesson = Lesson::factory()->preview()->create();

        $this->assertWidgetPresent($this->get(route('lessons.show', [$lesson->formation(), $lesson])));
    }

    public function test_it_is_on_account_payment_and_student_pages(): void
    {
        $this->enable();
        $formation = Formation::factory()->create();

        foreach (['register', 'login', 'password.request', 'consultation', 'legal.privacy', 'legal.terms', 'about', 'affiliate.program'] as $route) {
            $this->assertWidgetPresent($this->get(route($route)));
        }

        $this->assertWidgetPresent($this->get(route('checkout.show', $formation)));

        $unconfirmed = User::factory()->create(['email_verified_at' => null]);
        $this->assertWidgetPresent($this->actingAs($unconfirmed)->get(route('email-confirmation.show')));

        $student = User::factory()->create();
        foreach (['dashboard', 'student.formations.index', 'student.purchases.index', 'student.affiliate.index', 'student.profile', 'student.sessions.index', 'chat.index'] as $route) {
            $this->assertWidgetPresent($this->actingAs($student)->get(route($route)));
        }

        $this->assertWidgetPresent($this->actingAs($student)->get(route('checkout.show', $formation)));
        // Espace élève : le bouton passe au-dessus de la barre de navigation du bas.
        $this->actingAs($student)->get(route('dashboard'))->assertSee('has-bottom-nav', false)->assertSee('pb-40', false);
    }

    public function test_it_never_sits_on_the_floating_whatsapp_button(): void
    {
        $this->enable();
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        // Bouton WhatsApp flottant : seulement sur ordinateur (lg), en bas à droite ; sur téléphone il est dans l'en-tête.
        $this->assertSame(1, preg_match('/class="group fixed right-6 bottom-6 z-40 hidden [^"]*lg:flex/', $html));
        // Ama : en bas à droite sur téléphone et tablette, en bas à gauche à partir de lg.
        $this->assertSame(1, preg_match('/class="voice-assistant fixed right-4 [^"]*lg:right-auto [^"]*lg:left-6/', $html));
    }

    public function test_the_formation_page_keeps_room_for_the_buy_bar(): void
    {
        $this->enable();

        $this->get(route('formations.show', Formation::factory()->create()))
            ->assertOk()
            ->assertSee('has-sticky-cta', false)
            ->assertSee('Acheter maintenant');
    }

    public function test_the_admin_switches_it_on_and_off(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Assistante Ama');
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))
            ->assertOk()
            ->assertSee('Assistante activée')
            ->assertSee('Identifiant de l\'agent ElevenLabs')
            ->assertSee('value="'.self::AGENT.'"', false)
            ->assertDontSee('type="url"', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '1', 'agent_id' => self::AGENT])
            ->assertRedirect(route('admin.settings.edit', 'assistante'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(VoiceAssistant::enabled());
        $this->assertWidgetPresent($this->get(route('home')));
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'like', '%Assistante activée (agent ElevenLabs%')->exists());

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '0', 'agent_id' => self::AGENT])
            ->assertSessionHasNoErrors();

        $this->assertFalse(VoiceAssistant::enabled());
        auth()->logout();
        $this->assertWidgetAbsent($this->get(route('home')));
    }

    public function test_the_admin_sets_the_course_player_and_the_proactive_help(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))
            ->assertOk()
            ->assertSee('Afficher Ama dans le lecteur de cours')
            ->assertSee('Aide proactive')
            ->assertSee('name="help_delay"', false)
            ->assertSee('value="90"', false)
            ->assertSee('{{contexte}}', false)
            ->assertSee('{{message_accueil}}', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '1', 'agent_id' => self::AGENT, 'lessons' => '1', 'proactive' => '0', 'help_delay' => '120'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(VoiceAssistant::showInLessons());
        $this->assertFalse(VoiceAssistant::proactiveHelp());
        $this->assertSame(120, VoiceAssistant::helpDelay());
        $this->assertTrue(AdminActivity::query()->where('description', 'like', '%lecteur de cours : oui, aide proactive : non%')->exists());

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '1', 'agent_id' => self::AGENT, 'help_delay' => '5'])
            ->assertSessionHasErrors(['help_delay' => 'Le délai doit être d\'au moins 30 secondes.']);
    }

    public function test_the_agent_id_is_cleaned_before_validation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Espaces insécables, caractère de largeur nulle, retour à la ligne, majuscule du clavier.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'provider' => 'elevenlabs',
            'enabled' => '1',
            'agent_id' => "\u{00A0}\u{200B}Agent_9901m3phrpdwedt88vsdqmnwk09z \n",
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::AGENT, SiteSettings::get('site.voice_assistant.agent_id'));
    }

    public function test_the_agent_id_must_start_with_agent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->enable(false, 'agent_ancienidentifiant1');

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '1', 'agent_id' => '9901m3phrpdwedt88vsdqmnwk09z'])
            ->assertSessionHasErrors(['agent_id' => VoiceAssistant::invalidMessage('9901m3phrpdwedt88vsdqmnwk09z')]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '1', 'agent_id' => 'agent_abc-def!ghijkl'])
            ->assertSessionHasErrors('agent_id');

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '1', 'agent_id' => ''])
            ->assertSessionHasErrors('agent_id');

        // Rien n'a été enregistré.
        $this->assertSame(['enabled' => false, 'provider' => 'elevenlabs', 'agent_id' => 'agent_ancienidentifiant1'], SiteSettings::get('site.voice_assistant'));
        $this->assertStringContainsString('doit commencer par « agent_ »', VoiceAssistant::invalidMessage('9901m3phrpdwedt88vsdqmnwk09z'));
    }

    public function test_moderators_cannot_change_it(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->get(route('admin.settings.edit', 'assistante'))->assertForbidden();
        $this->actingAs($moderator)->put(route('admin.settings.update', 'assistante'), ['provider' => 'elevenlabs', 'enabled' => '1', 'agent_id' => self::AGENT])->assertForbidden();

        $this->assertFalse(VoiceAssistant::enabled());
    }

    public function test_clean_agent_id(): void
    {
        $this->assertSame(self::AGENT, VoiceAssistant::cleanAgentId(' '.self::AGENT.' '));
        $this->assertSame(self::AGENT, VoiceAssistant::cleanAgentId("agent_9901m3phrp\u{200B}dwedt88vsdqmnwk09z"));
        $this->assertSame(self::AGENT, VoiceAssistant::cleanAgentId('Identifiant : '.self::AGENT.'.'));
        $this->assertSame(self::AGENT, VoiceAssistant::cleanAgentId('https://elevenlabs.io/app/agents/agents/'.self::AGENT.'?tab=widget'));
        $this->assertSame('9901m3phrp', VoiceAssistant::cleanAgentId(' 9901 m3phrp '));
        $this->assertSame('', VoiceAssistant::cleanAgentId(null));

        $this->assertTrue(VoiceAssistant::isValidAgentId(self::AGENT));
        $this->assertFalse(VoiceAssistant::isValidAgentId('agent_'));
        $this->assertFalse(VoiceAssistant::isValidAgentId('9901m3phrpdwedt88vsdqmnwk09z'));
    }

    public function test_the_privacy_policy_mentions_the_assistant(): void
    {
        $this->enable(false);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Ama est une intelligence artificielle.')
            ->assertSee('traitées par le service ElevenLabs')
            ->assertSee('conservés 90 jours')
            ->assertSee('enregistre dans votre navigateur un identifiant technique de visiteur')
            ->assertSee('Pour vous guider, l\'assistante reçoit des informations sur la page que vous consultez', false)
            ->assertSee('sans aucune donnée personnelle');
    }

    public function test_the_widget_script_is_pinned_to_the_tested_version(): void
    {
        $this->assertSame('https://unpkg.com/@elevenlabs/convai-widget-embed@0.18.3', VoiceAssistant::SCRIPT_URL);

        $this->enable();
        $this->get(route('home'))->assertOk()->assertSee('convai-widget-embed@0.18.3', false);
    }
}
