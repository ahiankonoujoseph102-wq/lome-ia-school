<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\UserRole;
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
 * Assistante vocale IA (agent ElevenLabs) : interrupteur et identifiant dans l'admin, bouton flottant
 * sur les pages publiques choisies seulement, aucun script chargé tant qu'elle est désactivée.
 */
class VoiceAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const string AGENT = 'agent_9901m3phrpdwedt88vsdqmnwk09z';

    private function enable(bool $enabled = true, string $agentId = self::AGENT): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => $enabled, 'agent_id' => $agentId]]);
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

    public function test_it_is_absent_from_the_admin_the_course_player_and_other_pages(): void
    {
        $this->enable();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->assertWidgetAbsent($this->actingAs($admin)->get(route('admin.index')));
        $this->assertWidgetAbsent($this->actingAs($admin)->get(route('admin.settings.edit', 'coordonnees')));

        $lesson = Lesson::factory()->preview()->create();
        $this->assertWidgetAbsent($this->actingAs($admin)->get(route('lessons.show', [$lesson->formation(), $lesson])));

        auth()->logout();
        $this->assertWidgetAbsent($this->get(route('lessons.show', [$lesson->formation(), $lesson])));
        $this->assertWidgetAbsent($this->get(route('consultation')));
        $this->assertWidgetAbsent($this->get(route('legal.privacy')));

        $student = User::factory()->create();
        $this->assertWidgetAbsent($this->actingAs($student)->get(route('dashboard')));
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

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Assistante vocale');
        $this->actingAs($admin)->get(route('admin.settings.edit', 'assistante'))
            ->assertOk()
            ->assertSee('Assistante vocale activée')
            ->assertSee('Identifiant de l\'agent ElevenLabs')
            ->assertSee('value="'.self::AGENT.'"', false)
            ->assertDontSee('type="url"', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'agent_id' => self::AGENT])
            ->assertRedirect(route('admin.settings.edit', 'assistante'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(VoiceAssistant::enabled());
        $this->assertWidgetPresent($this->get(route('home')));
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'like', '%Assistante vocale activée%')->exists());

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '0', 'agent_id' => self::AGENT])
            ->assertSessionHasNoErrors();

        $this->assertFalse(VoiceAssistant::enabled());
        auth()->logout();
        $this->assertWidgetAbsent($this->get(route('home')));
    }

    public function test_the_agent_id_is_cleaned_before_validation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Espaces insécables, caractère de largeur nulle, retour à la ligne, majuscule du clavier.
        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), [
            'enabled' => '1',
            'agent_id' => "\u{00A0}\u{200B}Agent_9901m3phrpdwedt88vsdqmnwk09z \n",
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::AGENT, SiteSettings::get('site.voice_assistant.agent_id'));
    }

    public function test_the_agent_id_must_start_with_agent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->enable(false, 'agent_ancienidentifiant1');

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'agent_id' => '9901m3phrpdwedt88vsdqmnwk09z'])
            ->assertSessionHasErrors(['agent_id' => VoiceAssistant::invalidMessage('9901m3phrpdwedt88vsdqmnwk09z')]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'agent_id' => 'agent_abc-def!ghijkl'])
            ->assertSessionHasErrors('agent_id');

        $this->actingAs($admin)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'agent_id' => ''])
            ->assertSessionHasErrors('agent_id');

        // Rien n'a été enregistré.
        $this->assertSame(['enabled' => false, 'agent_id' => 'agent_ancienidentifiant1'], SiteSettings::get('site.voice_assistant'));
        $this->assertStringContainsString('doit commencer par « agent_ »', VoiceAssistant::invalidMessage('9901m3phrpdwedt88vsdqmnwk09z'));
    }

    public function test_moderators_cannot_change_it(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->get(route('admin.settings.edit', 'assistante'))->assertForbidden();
        $this->actingAs($moderator)->put(route('admin.settings.update', 'assistante'), ['enabled' => '1', 'agent_id' => self::AGENT])->assertForbidden();

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
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Ama est une intelligence artificielle.')
            ->assertSee('traitées par le service ElevenLabs')
            ->assertSee('conservés 90 jours');
    }
}
