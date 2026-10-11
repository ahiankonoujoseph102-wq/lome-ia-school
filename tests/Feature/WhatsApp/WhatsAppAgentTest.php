<?php

namespace Tests\Feature\WhatsApp;

use App\Admin\AdminMenu;
use App\Assistant\AmaPrompt;
use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Mail\WhatsAppHandoffMail;
use App\Models\AdminActivity;
use App\Models\AssistantUsage;
use App\Models\Formation;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Settings\SiteSettings;
use App\Support\Money;
use App\WhatsApp\WhatsAppAgent;
use App\WhatsApp\WhatsAppPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Agent WhatsApp : webhook de Meta (vérification, signature), réponses de Gemini envoyées par l'API WhatsApp
 * Business Cloud, périmètre limité à l'école, passage à l'équipe, limites, aucune donnée personnelle chez Google,
 * page admin « Conversations WhatsApp » et réglages. Faux Meta et faux Gemini (Http::fake) : aucun vrai appel.
 */
class WhatsAppAgentTest extends TestCase
{
    use RefreshDatabase;

    private const string GEMINI_KEY = 'cle-de-test-GEMINI-9f8e7d6c5b4a';

    private const string TOKEN = 'EAAG-jeton-de-test-WHATSAPP-123456789';

    private const string SECRET = 'cle-secrete-de-test-application-meta';

    private const string VERIFY = 'mot-de-controle-de-test-42';

    private const string PHONE_ID = '109876543210';

    private const string CLIENT = '22890123456';

    private int $sent = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Mail::fake();
        config([
            'services.gemini.key' => self::GEMINI_KEY,
            'services.gemini.model' => 'modele-flash-de-test',
            'services.whatsapp.token' => self::TOKEN,
            'services.whatsapp.phone_number_id' => self::PHONE_ID,
            'services.whatsapp.app_secret' => self::SECRET,
            'services.whatsapp.verify_token' => self::VERIFY,
        ]);
        SiteSettings::save(['site.whatsapp_agent' => ['enabled' => true]]);
    }

    private function fake(string $reply = 'Bonjour, je suis Ama. La formation coûte 25 000 FCFA.', int $geminiStatus = 200): void
    {
        $this->sent = 0;

        Http::fake([
            'generativelanguage.googleapis.com/*' => $geminiStatus === 200
                ? Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $reply]]], 'finishReason' => 'STOP']]])
                : Http::response(['error' => ['code' => $geminiStatus, 'message' => 'Erreur', 'status' => 'INTERNAL']], $geminiStatus),
            'graph.facebook.com/*' => function (Request $request) {
                if (($request->data()['status'] ?? null) === 'read') {
                    return Http::response(['success' => true]);
                }

                return Http::response(['messaging_product' => 'whatsapp', 'contacts' => [['wa_id' => self::CLIENT]], 'messages' => [['id' => 'wamid.SORTANT'.(++$this->sent)]]]);
            },
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private static function payload(array $message = [], string $phoneId = self::PHONE_ID): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '22870000000', 'phone_number_id' => $phoneId],
            'contacts' => [['profile' => ['name' => 'Kofi Mensah'], 'wa_id' => self::CLIENT]],
            'messages' => [[
                'from' => self::CLIENT,
                'id' => 'wamid.ENTRANT'.uniqid(),
                'timestamp' => (string) now()->getTimestamp(),
                'type' => 'text',
                'text' => ['body' => 'Bonjour, combien coûte la formation ?'],
                ...$message,
            ]],
        ]]]]]];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = (string) json_encode($payload);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function say(string $text): TestResponse
    {
        return $this->webhook(self::payload(['text' => ['body' => $text]]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sentTexts(): array
    {
        return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'graph.facebook.com') && ($request->data()['type'] ?? null) === 'text')
            ->map(fn (array $pair): array => $pair[0]->data())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function geminiBodies(): array
    {
        return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'generativelanguage'))
            ->map(fn (array $pair): array => $pair[0]->data())
            ->values()
            ->all();
    }

    public function test_meta_can_verify_the_webhook_address_with_our_control_word_only(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token='.self::VERIFY.'&hub.challenge=1158201444')
            ->assertOk()
            ->assertSeeText('1158201444');

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=mauvais&hub.challenge=1158201444')->assertForbidden();

        config(['services.whatsapp.verify_token' => null]);
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=1158201444')->assertForbidden();

        $this->assertSame(url('/webhooks/whatsapp'), route('whatsapp.webhook'));
    }

    public function test_a_message_without_a_valid_meta_signature_is_refused(): void
    {
        $this->fake();

        $this->webhook(self::payload(), 'sha256='.str_repeat('0', 64))->assertForbidden();
        $this->webhook(self::payload(), '')->assertForbidden();

        config(['services.whatsapp.app_secret' => null]);
        $this->webhook(self::payload())->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::query()->count());
        Http::assertNothingSent();
    }

    public function test_the_agent_answers_with_gemini_without_sending_personal_data_to_google(): void
    {
        $this->fake();
        Formation::factory()->create(['title' => 'Formation Agents IA', 'price' => 25000]);

        $this->say('Bonjour, mon numéro est +228 90 12 34 56 et mon e-mail kofi@exemple.com. Combien coûte Agents IA ?')
            ->assertOk()
            ->assertSeeText('OK');

        // Réponse envoyée au client par l'API de Meta, avec le jeton dans l'en-tête seulement.
        $this->assertSame([[
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => self::CLIENT,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => 'Bonjour, je suis Ama. La formation coûte 25 000 FCFA.'],
        ]], $this->sentTexts());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'graph.facebook.com/v24.0/'.self::PHONE_ID.'/messages')
            && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && ! str_contains($request->url(), self::TOKEN));
        // Message marqué comme lu, avec « en train d'écrire ».
        Http::assertSent(fn (Request $request): bool => ($request->data()['status'] ?? null) === 'read' && ($request->data()['typing_indicator']['type'] ?? null) === 'text');

        // Ce que Gemini reçoit : connaissances de l'école, règles du périmètre, présentation (premier message),
        // et le message nettoyé : ni numéro, ni e-mail, ni nom du profil.
        $body = (string) json_encode($this->geminiBodies()[0], JSON_UNESCAPED_UNICODE);
        $instructions = (string) $this->geminiBodies()[0]['system_instruction']['parts'][0]['text'];
        $this->assertStringContainsString('Règle du périmètre', $instructions);
        $this->assertStringContainsString('WhatsApp interdit les assistants IA généralistes', $instructions);
        $this->assertStringContainsString('Formation Agents IA', $instructions);
        $this->assertStringContainsString(Money::fcfa(25000), $instructions);
        $this->assertStringContainsString(WhatsAppPrompt::TEAM_MARKER, $instructions);
        $this->assertStringContainsString('première réponse à cette personne', $instructions);
        $this->assertStringContainsString('[numéro]', $body);
        $this->assertStringContainsString('[adresse e-mail]', $body);
        foreach (['90 12 34 56', self::CLIENT, 'kofi@exemple.com', 'Kofi Mensah', self::TOKEN] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }

        $contact = WhatsAppContact::query()->sole();
        $this->assertSame('Kofi Mensah', $contact->profile_name);
        $this->assertSame([WhatsAppMessage::CLIENT, WhatsAppMessage::AGENT], $contact->messages->pluck('sender')->all());
        $this->assertSame('sent', $contact->messages->last()?->status);
        $this->assertSame(1, AssistantUsage::requestsToday());
        $this->assertNull($contact->needs_human_at);
    }

    public function test_the_second_reply_does_not_introduce_itself_again(): void
    {
        $this->fake();

        $this->say('Bonjour');
        $this->say('Et la consultation gratuite ?');

        $bodies = $this->geminiBodies();
        $this->assertCount(2, $bodies);
        $this->assertStringNotContainsString('première réponse à cette personne', (string) $bodies[1]['system_instruction']['parts'][0]['text']);
        // Conversation transmise : client, agent, client.
        $this->assertSame(['user', 'model', 'user'], array_column($bodies[1]['contents'], 'role'));
    }

    public function test_a_message_received_twice_gets_a_single_reply(): void
    {
        $this->fake();
        $payload = self::payload();

        $this->webhook($payload)->assertOk();
        $this->webhook($payload)->assertOk();

        $this->assertCount(1, $this->sentTexts());
        $this->assertSame(1, WhatsAppMessage::query()->where('sender', WhatsAppMessage::CLIENT)->count());
    }

    public function test_messages_for_another_number_are_ignored(): void
    {
        $this->fake();

        $this->webhook(self::payload([], '999999999'))->assertOk();

        $this->assertSame(0, WhatsAppContact::query()->count());
        Http::assertNothingSent();
    }

    public function test_switched_off_the_agent_stays_silent_but_messages_are_kept(): void
    {
        $this->fake();
        SiteSettings::save(['site.whatsapp_agent' => ['enabled' => false]]);

        $this->say('Bonjour ?')->assertOk();

        $this->assertSame(1, WhatsAppMessage::query()->where('sender', WhatsAppMessage::CLIENT)->count());
        Http::assertNothingSent();
    }

    public function test_a_voice_note_or_an_image_without_text_gets_a_polite_request_to_write(): void
    {
        $this->fake();

        $this->webhook(self::payload(['type' => 'audio', 'audio' => ['id' => 'media-1', 'mime_type' => 'audio/ogg'], 'text' => null]))->assertOk();

        $this->assertSame(WhatsAppAgent::MEDIA_REPLY, $this->sentTexts()[0]['text']['body']);
        $this->assertSame([], $this->geminiBodies());
        $this->assertSame('[Note vocale reçue]', WhatsAppMessage::query()->where('sender', WhatsAppMessage::CLIENT)->sole()->displayText());
    }

    public function test_reactions_get_no_reply(): void
    {
        $this->fake();

        $this->webhook(self::payload(['type' => 'reaction', 'reaction' => ['message_id' => 'wamid.X', 'emoji' => '👍'], 'text' => null]))->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_the_agent_hands_over_to_the_team_and_then_stays_silent(): void
    {
        $this->fake("Je transmets votre demande à l'équipe, qui vous répondra ici même.\n[EQUIPE] Paiement envoyé hier, accès pas encore ouvert.");

        $this->say('J\'ai payé hier et je n\'ai toujours pas accès, je veux parler à quelqu\'un');

        $this->assertSame('Je transmets votre demande à l\'équipe, qui vous répondra ici même.', $this->sentTexts()[0]['text']['body']);

        $contact = WhatsAppContact::query()->sole();
        $this->assertNotNull($contact->needs_human_at);
        $this->assertSame('Paiement envoyé hier, accès pas encore ouvert.', $contact->needs_human_reason);
        $this->assertFalse($contact->agentActive());
        Mail::assertSent(WhatsAppHandoffMail::class, fn (WhatsAppHandoffMail $mail): bool => $mail->hasTo((string) config('site.payments.admin_email'))
            && str_contains($mail->render(), '+228 90 12 34 56')
            && str_contains($mail->render(), 'Paiement envoyé hier'));

        // L'équipe a la main : l'agent ne répond plus à ce client.
        $this->say('Allô ?');
        $this->assertCount(1, $this->sentTexts());

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->assertSame(1, AdminMenu::todoTotal($admin));
    }

    public function test_a_gemini_failure_sends_a_waiting_message_and_warns_the_team(): void
    {
        $this->fake(geminiStatus: 500);

        $this->say('Bonjour');

        $this->assertSame(WhatsAppAgent::handoffNotice(), $this->sentTexts()[0]['text']['body']);
        $this->assertStringContainsString('du lundi au samedi', WhatsAppAgent::handoffNotice());
        $this->assertNotNull(WhatsAppContact::query()->sole()->needs_human_at);
        Mail::assertSent(WhatsAppHandoffMail::class);
    }

    public function test_the_daily_limit_per_client_hands_over_to_the_team(): void
    {
        $this->fake();
        SiteSettings::save(['site.whatsapp_agent' => ['enabled' => true, 'contact_daily_limit' => 5]]);

        $contact = WhatsAppContact::query()->create(['wa_id' => self::CLIENT]);
        for ($i = 0; $i < 5; $i++) {
            WhatsAppMessage::query()->create(['whatsapp_contact_id' => $contact->id, 'sender' => WhatsAppMessage::AGENT, 'content' => 'Réponse '.$i]);
        }

        $this->say('Encore une question');

        $this->assertSame([], $this->geminiBodies());
        $this->assertSame(WhatsAppAgent::handoffNotice(), $this->sentTexts()[0]['text']['body']);
        $this->assertNotNull($contact->refresh()->needs_human_at);
    }

    public function test_the_gemini_daily_limit_is_shared_with_ama(): void
    {
        $this->fake();
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'daily_limit' => 20]]);
        AssistantUsage::query()->create(['day' => AssistantUsage::today(), 'requests' => 20, 'failures' => 0]);

        $this->say('Bonjour');

        $this->assertSame([], $this->geminiBodies());
        $this->assertSame(WhatsAppAgent::handoffNotice(), $this->sentTexts()[0]['text']['body']);
    }

    public function test_an_old_message_delivered_late_gets_no_automatic_reply(): void
    {
        $this->fake();

        $this->webhook(self::payload(['timestamp' => (string) now()->subHours(3)->getTimestamp()]))->assertOk();

        Http::assertNothingSent();
        $this->assertSame(1, WhatsAppMessage::query()->count());
    }

    public function test_delivery_statuses_only_move_forward_and_failures_are_kept(): void
    {
        $this->fake();
        $this->say('Bonjour');
        $reply = WhatsAppMessage::query()->where('sender', WhatsAppMessage::AGENT)->sole();

        $statuses = fn (array $statuses): array => ['entry' => [['changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => self::PHONE_ID],
            'statuses' => $statuses,
        ]]]]]];

        $this->webhook($statuses([['id' => $reply->wa_message_id, 'status' => 'read']]))->assertOk();
        $this->webhook($statuses([['id' => $reply->wa_message_id, 'status' => 'delivered']]))->assertOk();
        $this->assertSame('read', $reply->refresh()->status);

        $this->webhook($statuses([['id' => $reply->wa_message_id, 'status' => 'failed', 'errors' => [['code' => 131047, 'title' => 'Re-engagement message']]]]))->assertOk();
        $this->assertSame('failed', $reply->refresh()->status);
        $this->assertSame('131047 Re-engagement message', $reply->error);
    }

    public function test_secret_codes_are_masked_before_being_stored(): void
    {
        $this->fake();

        $this->say('Mon code secret est 4589, je n\'arrive pas à payer');

        $stored = (string) WhatsAppMessage::query()->where('sender', WhatsAppMessage::CLIENT)->sole()->content;
        $this->assertStringNotContainsString('4589', $stored);
        $this->assertStringContainsString('[masqué]', $stored);
    }

    public function test_the_prompt_keeps_the_agent_inside_the_school_and_keeps_amas_rules(): void
    {
        $prompt = WhatsAppPrompt::build();

        $this->assertStringContainsString('pas un assistant à tout faire', $prompt);
        $this->assertStringContainsString('rédiger un texte, un CV', $prompt);
        $this->assertStringContainsString('Règle « Objections »', $prompt);
        $this->assertStringContainsString('Règle « Ton »', $prompt);
        $this->assertStringContainsString(AmaPrompt::UNKNOWN_MARKER, $prompt);
        $this->assertStringContainsString('## Canal', $prompt);
        $this->assertStringNotContainsString('Contexte de la page du visiteur', $prompt);
        // Jamais la ligne technique de la carte du site, ni le bouton « Envoyer à Coach Joseph ».
        $this->assertStringNotContainsString('[TRANSMETTRE]', $prompt);

        $this->assertSame(['text' => 'Bonjour.', 'team' => true, 'summary' => 'Demande de remboursement.'], WhatsAppPrompt::extractTeamRequest("Bonjour.\n[ÉQUIPE] Demande de remboursement."));
        $this->assertSame(['text' => 'Bonjour.', 'team' => false, 'summary' => null], WhatsAppPrompt::extractTeamRequest('Bonjour.'));
    }

    public function test_the_admin_reads_answers_and_takes_over_a_conversation(): void
    {
        $this->fake("Je transmets à l'équipe.\n[EQUIPE] Question sur un remboursement.");
        $this->say('Je veux un remboursement');
        $contact = WhatsAppContact::query()->sole();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Kofi Mensah')
            ->assertSee('Attendent l\'équipe (1)');
        $this->actingAs($admin)->get(route('admin.whatsapp.show', $contact))
            ->assertOk()
            ->assertSee('Je veux un remboursement')
            ->assertSee('Question sur un remboursement.')
            ->assertSee('+228 90 12 34 56');

        $this->actingAs($admin)->post(route('admin.whatsapp.reply', $contact), ['message' => 'Bonjour Kofi, je regarde votre dossier.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Bonjour Kofi, je regarde votre dossier.', $this->sentTexts()[1]['text']['body']);
        $contact->refresh();
        $this->assertNull($contact->needs_human_at);
        $this->assertFalse($contact->agentActive());
        $this->assertSame($admin->id, WhatsAppMessage::query()->where('sender', WhatsAppMessage::TEAM)->sole()->user_id);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::WhatsAppReplied)->exists());

        // Arrêter puis relancer l'agent pour ce client.
        $this->actingAs($admin)->post(route('admin.whatsapp.agent', $contact), ['action' => 'stop'])->assertRedirect();
        $this->assertTrue($contact->refresh()->agent_stopped);
        $this->actingAs($admin)->post(route('admin.whatsapp.agent', $contact), ['action' => 'resume'])->assertRedirect();
        $this->assertTrue($contact->refresh()->agentActive());

        $this->say('Merci !');
        $this->assertCount(3, $this->sentTexts());
    }

    public function test_the_team_cannot_reply_freely_after_24_hours(): void
    {
        $this->fake();
        $contact = WhatsAppContact::query()->create(['wa_id' => self::CLIENT, 'last_inbound_at' => now()->subHours(25)]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.whatsapp.show', $contact))->assertSee('data-test="whatsapp-window-closed"', false);
        $this->actingAs($admin)->post(route('admin.whatsapp.reply', $contact), ['message' => 'Bonjour'])->assertSessionHasErrors('message');

        Http::assertNothingSent();
    }

    public function test_moderators_and_students_cannot_see_whatsapp_conversations(): void
    {
        $contact = WhatsAppContact::query()->create(['wa_id' => self::CLIENT]);

        $this->get(route('admin.whatsapp.index'))->assertRedirect(route('login'));

        foreach ([User::factory()->create(['role' => UserRole::Moderator]), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.whatsapp.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.whatsapp.show', $contact))->assertForbidden();
            $this->actingAs($user)->post(route('admin.whatsapp.reply', $contact), ['message' => 'x'])->assertForbidden();
        }
    }

    public function test_the_settings_screen_shows_what_is_missing_without_showing_any_secret(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        config(['services.whatsapp.app_secret' => null]);

        $html = (string) $this->actingAs($admin)->get(route('admin.settings.edit', 'whatsapp'))
            ->assertOk()
            ->assertSee('Clé secrète de l\'application (WHATSAPP_APP_SECRET) : manquant')
            ->assertSee('Jeton d\'accès (WHATSAPP_ACCESS_TOKEN) : présent')
            ->assertSee(url('/webhooks/whatsapp'))
            ->getContent();

        foreach ([self::TOKEN, self::VERIFY, self::GEMINI_KEY, self::PHONE_ID] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        $this->actingAs($admin)->put(route('admin.settings.update', 'whatsapp'), [
            'enabled' => '0',
            'instructions' => '',
            'contact_daily_limit' => '12',
            'pause_hours' => '48',
        ])->assertRedirect(route('admin.settings.edit', 'whatsapp'));

        $this->assertFalse((bool) config('site.whatsapp_agent.enabled'));
        $this->assertSame(WhatsAppPrompt::DEFAULT_INSTRUCTIONS, config('site.whatsapp_agent.instructions'));
        $this->assertSame(12, config('site.whatsapp_agent.contact_daily_limit'));
        $this->assertTrue(AdminActivity::query()->where('description', 'like', 'Agent WhatsApp désactivé (12 réponses%')->exists());
    }

    public function test_conversations_are_erased_90_days_after_the_last_message(): void
    {
        $old = WhatsAppContact::query()->create(['wa_id' => '22891111111', 'last_message_at' => now()->subDays(91)]);
        WhatsAppMessage::query()->create(['whatsapp_contact_id' => $old->id, 'sender' => WhatsAppMessage::CLIENT, 'content' => 'Ancien']);
        WhatsAppContact::query()->create(['wa_id' => '22892222222', 'last_message_at' => now()->subDays(10)]);

        $this->artisan('model:prune', ['--model' => [WhatsAppContact::class]])->assertSuccessful();

        $this->assertSame(['22892222222'], WhatsAppContact::query()->pluck('wa_id')->all());
        $this->assertSame(0, WhatsAppMessage::query()->count());
    }

    public function test_the_privacy_policy_explains_the_whatsapp_assistant_only_when_it_is_switched_on(): void
    {
        $this->get(route('legal.privacy'))->assertOk()->assertSee('data-test="privacy-whatsapp-agent"', false);

        SiteSettings::save(['site.whatsapp_agent' => ['enabled' => false]]);
        $this->get(route('legal.privacy'))->assertOk()->assertDontSee('data-test="privacy-whatsapp-agent"', false);
    }
}
