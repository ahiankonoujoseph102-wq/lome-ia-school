<?php

namespace Tests\Feature\Site;

use App\Admin\AdminMenu;
use App\Assistant\AmaPrompt;
use App\Assistant\FounderHandoff;
use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Mail\FounderHandoffMail;
use App\Models\AdminActivity;
use App\Models\AssistantConversation;
use App\Models\AssistantHandoff;
use App\Models\AssistantMessage;
use App\Models\AssistantUsage;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * « Transmettre au fondateur » : Ama connaît les coordonnées publiques, propose de transmettre une question qu'elle
 * ne sait pas traiter, récapitule et affiche une carte de confirmation ; la demande ne part qu'au toucher de
 * « Envoyer à Coach Joseph » (format vérifié par le serveur, 3 par jour, champ piège), elle est enregistrée pour
 * l'admin (« Demandes transmises ») et envoyée tout de suite par e-mail. Faux Gemini (Http::fake) : aucun vrai appel.
 */
class AmaHandoffTest extends TestCase
{
    use RefreshDatabase;

    private const string KEY = 'cle-de-test-GEMINI-transmettre-7d6c';

    private const string VISITOR = 'cccccccccccccccccccccccccccccccccccccccc';

    private const string RECAP = 'Je récapitule : Awa, réponse par e-mail, question sur une formation en comptabilité. Vérifiez, puis touchez « Envoyer à Coach Joseph ».';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Mail::fake();
        config(['services.gemini.key' => self::KEY, 'services.gemini.model' => 'modele-flash-de-test']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function reply(string $text): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => 'STOP']]];
    }

    /**
     * @param  list<string>  $replies  réponses successives de Gemini
     */
    private function fakeGemini(array $replies): void
    {
        $sequence = Http::fakeSequence('generativelanguage.googleapis.com/*');

        foreach ($replies as $reply) {
            $sequence->push(self::reply($reply));
        }
    }

    private static function marker(string $firstName = 'Awa', string $channel = 'email', string $contact = ''): string
    {
        return "\n".FounderHandoff::MARKER.' '.json_encode(['prenom' => $firstName, 'canal' => $channel, 'contact' => $contact, 'question' => 'Proposez-vous une formation en comptabilité ?'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ask(string $message, array $data = [], string $visitor = self::VISITOR): TestResponse
    {
        return $this->withCredentials()->withCookie('ama_visitor', $visitor)->postJson(route('assistant.message'), ['message' => $message, ...$data]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function submit(array $data = [], string $visitor = self::VISITOR): TestResponse
    {
        return $this->withCredentials()->withCookie('ama_visitor', $visitor)->postJson(route('assistant.handoff'), [
            'first_name' => 'Awa',
            'channel' => 'email',
            'contact' => 'awa.kodjo@example.com',
            'question' => 'Proposez-vous une formation en comptabilité ?',
            'source' => 'chat',
            ...$data,
        ]);
    }

    /**
     * Conversation où le visiteur accepte la transmission et donne son adresse e-mail : carte proposée.
     */
    private function conversationWithCard(): TestResponse
    {
        $this->fakeGemini([
            '[INCONNU] Je n\'ai pas cette information, mais je peux transmettre votre question à notre fondateur, Coach Joseph. Il vous répondra personnellement. Comment voulez-vous recevoir sa réponse : par e-mail ou sur WhatsApp ?',
            'Très bien. Quel est votre prénom, et quelle est votre adresse e-mail ?',
            self::RECAP.self::marker(),
        ]);

        $id = $this->ask('Proposez-vous une formation en comptabilité ?')->json('conversation');
        $this->ask('Oui, par e-mail', ['conversation' => $id]);

        return $this->ask('Awa, mon e-mail est awa.kodjo@example.com', ['conversation' => $id])->assertOk();
    }

    public function test_ama_knows_and_gives_the_public_contact_details(): void
    {
        $this->fakeGemini(['Vous pouvez joindre Coach Joseph au +228 72 24 02 31, sur WhatsApp ou par téléphone.']);

        $this->ask('Quel est le numéro de votre fondateur ?')->assertOk()->assertJsonPath('reply', 'Vous pouvez joindre Coach Joseph au +228 72 24 02 31, sur WhatsApp ou par téléphone.');

        Http::assertSent(function (Request $request): bool {
            $instructions = (string) data_get($request->data(), 'system_instruction.parts.0.text');

            foreach (['+228 72 24 02 31', '+228 79 16 23 90', '+228 79 74 21 27', 'academyjoseph71@gmail.com', 'Adidogomé, Lycée moderne 2, Lomé, Togo', 'Du lundi au samedi, de 8 h à 20 h', 'Pour joindre le fondateur, Coach Joseph (Joseph Ahiankonou', 'donne-les quand on te les demande', 'tu donnes les coordonnées publiques'] as $expected) {
                if (! str_contains($instructions, $expected)) {
                    $this->fail('Absent de ce qu\'Ama reçoit : '.$expected);
                }
            }

            return true;
        });
    }

    public function test_the_contact_details_follow_the_site_settings(): void
    {
        SiteSettings::save(['site.contact' => [...config('site.contact'), 'phones' => ['+228 70 00 11 22'], 'email' => 'contact@exemple.tg']]);

        $prompt = AmaPrompt::build([]);

        $this->assertStringContainsString('+228 70 00 11 22', $prompt);
        $this->assertStringContainsString('contact@exemple.tg', $prompt);
    }

    public function test_the_handoff_rule_is_always_sent_even_with_custom_instructions(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini', 'instructions' => 'Sois brève. Tu ne demandes aucune donnée personnelle : ni nom, ni numéro, ni adresse e-mail.']]);

        $prompt = AmaPrompt::build([]);

        $this->assertStringContainsString('Sois brève.', $prompt);
        $this->assertStringContainsString('Règle « Transmettre au fondateur » (elle prime sur toute autre consigne sur les données personnelles)', $prompt);
        $this->assertStringContainsString('je peux transmettre votre question à notre fondateur, Coach Joseph. Il vous répondra personnellement. Comment voulez-vous recevoir sa réponse : par e-mail ou sur WhatsApp ?', $prompt);
        $this->assertStringContainsString(FounderHandoff::MARKER, $prompt);
        $this->assertStringContainsString('Ne demande jamais de code, de mot de passe ni d\'information de paiement.', $prompt);

        // Mode appel : jamais d'adresse ni de numéro à voix haute, la fiche s'affiche à la fin de l'appel.
        $this->assertStringContainsString('jamais son adresse ni son numéro à voix haute', AmaPrompt::build([], call: true));
    }

    public function test_the_default_instructions_offer_the_handoff(): void
    {
        $this->assertStringContainsString('Tu proposes ensuite de transmettre la question à notre fondateur, Coach Joseph', AmaPrompt::DEFAULT_INSTRUCTIONS);
        $this->assertStringNotContainsString('ni nom, ni numéro, ni adresse e-mail', AmaPrompt::DEFAULT_INSTRUCTIONS);
        $this->assertSame(AmaPrompt::DEFAULT_INSTRUCTIONS, AmaPrompt::effectiveInstructions(''));
    }

    public function test_the_card_is_proposed_with_the_contact_that_never_reaches_gemini(): void
    {
        $response = $this->conversationWithCard();

        $response->assertJson([
            'reply' => self::RECAP,
            'fallback' => false,
            'handoff' => [
                'first_name' => 'Awa',
                'channel' => 'email',
                'contact' => 'awa.kodjo@example.com',
                'question' => 'Proposez-vous une formation en comptabilité ?',
            ],
        ]);
        $this->assertStringNotContainsString('TRANSMETTRE', (string) $response->getContent());

        // L'adresse n'est jamais partie chez Google, ni enregistrée dans la conversation.
        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->body(), 'awa.kodjo'));
        $this->assertFalse(AssistantMessage::query()->where('content', 'like', '%awa.kodjo%')->exists());
        $this->assertFalse(AssistantMessage::query()->where('content', 'like', '%TRANSMETTRE]%')->exists());
        $this->assertSame(self::RECAP, AssistantMessage::query()->where('role', AssistantMessage::AMA)->latest('id')->value('content'));

        // La question sans réponse est notée pour l'admin.
        $this->assertTrue(AssistantMessage::query()->where('unanswered', true)->exists());

        // Rien n'est envoyé tant que le visiteur ne touche pas « Envoyer à Coach Joseph ».
        $this->assertSame(0, AssistantHandoff::query()->count());
        Mail::assertNothingSent();
    }

    public function test_a_wrong_email_makes_ama_ask_again_politely(): void
    {
        // « awa@gmail » n'est pas une adresse complète : elle parvient telle quelle à Ama, qui la recopie.
        $this->fakeGemini([self::RECAP.self::marker(contact: 'awa@gmail')]);

        $this->ask('Awa, mon e-mail est awa@gmail')->assertOk()
            ->assertJsonPath('handoff', null)
            ->assertJsonPath('reply', 'Il me semble que cette adresse e-mail n\'est pas complète. Pouvez-vous me la réécrire ? Par exemple : prenom@gmail.com');

        $this->assertSame(FounderHandoff::invalidMessage('email'), AssistantMessage::query()->where('role', AssistantMessage::AMA)->value('content'));
    }

    public function test_a_whatsapp_number_without_country_code_is_asked_again(): void
    {
        $this->fakeGemini([
            'Je récapitule : Awa, réponse sur WhatsApp.'.self::marker(channel: 'whatsapp'),
            'Je récapitule : Awa, réponse sur WhatsApp.'.self::marker(channel: 'whatsapp'),
        ]);

        $id = $this->ask('Awa, mon WhatsApp : 93 11 22 33')->assertOk()
            ->assertJsonPath('handoff', null)
            ->assertJsonPath('reply', FounderHandoff::invalidMessage('whatsapp'))
            ->json('conversation');
        $this->assertStringContainsString('avec l\'indicatif du pays', FounderHandoff::invalidMessage('whatsapp'));

        $this->ask('+228 93 11 22 33', ['conversation' => $id])->assertOk()
            ->assertJsonPath('handoff.channel', 'whatsapp')
            ->assertJsonPath('handoff.contact', '+228 93 11 22 33');

        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->body(), '93 11 22 33'));
    }

    public function test_missing_information_is_asked_before_the_card(): void
    {
        $this->fakeGemini([
            'Je récapitule.'.self::marker(firstName: ''),
            'Je récapitule.'.self::marker(channel: ''),
            'Je récapitule.'.self::marker(),
        ]);

        $this->ask('Je veux transmettre ma question')->assertJsonPath('reply', 'Pour transmettre votre question à Coach Joseph, quel est votre prénom ?')->assertJsonPath('handoff', null);
        $this->ask('Awa')->assertJsonPath('reply', 'Comment voulez-vous recevoir la réponse de Coach Joseph : par e-mail ou sur WhatsApp ?');
        $this->ask('Par e-mail')->assertJsonPath('reply', 'Pour que Coach Joseph puisse vous répondre, quelle est votre adresse e-mail ? Par exemple : prenom@gmail.com');
    }

    public function test_the_request_is_sent_only_when_the_visitor_touches_send(): void
    {
        $id = $this->conversationWithCard()->json('conversation');

        $this->submit(['conversation' => $id, 'context' => ['page' => 'page d\'accueil']])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'reply' => 'C\'est transmis ! Coach Joseph vous répondra dès que possible, du lundi au samedi de 8 h à 20 h. Merci pour votre patience.']);

        $handoff = AssistantHandoff::query()->sole();
        $this->assertSame('Awa', $handoff->first_name);
        $this->assertSame('email', $handoff->channel);
        $this->assertSame('awa.kodjo@example.com', $handoff->email);
        $this->assertNull($handoff->phone);
        $this->assertSame('Proposez-vous une formation en comptabilité ?', $handoff->question);
        $this->assertSame('chat', $handoff->source);
        $this->assertTrue($handoff->isPending());
        $this->assertSame(AssistantConversation::query()->sole()->id, $handoff->assistant_conversation_id);
        // Extrait de la conversation : textes déjà nettoyés (jamais l'adresse écrite par le visiteur).
        $this->assertStringContainsString('Visiteur : Proposez-vous une formation en comptabilité ?', (string) $handoff->excerpt);
        $this->assertStringContainsString('Ama : '.self::RECAP, (string) $handoff->excerpt);
        $this->assertStringNotContainsString('awa.kodjo', (string) $handoff->excerpt);

        // Ama le dit aussi dans la conversation (Gemini le saura au message suivant).
        $this->assertStringStartsWith('C\'est transmis !', (string) AssistantMessage::query()->latest('id')->value('content'));

        Mail::assertSent(FounderHandoffMail::class, function (FounderHandoffMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('academyjoseph71@gmail.com')
                && $mail->hasReplyTo('awa.kodjo@example.com')
                && str_contains($html, 'Proposez-vous une formation en comptabilité ?')
                && str_contains($html, 'Par e-mail')
                && str_contains($html, 'page d')
                && str_contains($html, 'Extrait de la conversation')
                && ! str_contains($html, 'Répondre sur WhatsApp');
        });
    }

    public function test_a_whatsapp_answer_gives_a_reply_on_whatsapp_button(): void
    {
        $this->submit(['channel' => 'whatsapp', 'contact' => '+228 91 23 45 67', 'source' => 'form'])->assertJsonPath('ok', true);

        $handoff = AssistantHandoff::query()->sole();
        $this->assertSame('+22891234567', $handoff->phone);
        $this->assertNull($handoff->email);
        $this->assertNull($handoff->assistant_conversation_id);

        Mail::assertSent(FounderHandoffMail::class, function (FounderHandoffMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('academyjoseph71@gmail.com')
                && $mail->envelope()->replyTo === []
                && str_contains($html, 'Répondre sur WhatsApp')
                && str_contains($html, 'https://wa.me/22891234567?text=');
        });
    }

    public function test_the_server_checks_the_format_before_sending(): void
    {
        $this->submit(['contact' => 'awa@gmail'])
            ->assertOk()
            ->assertJson(['ok' => false, 'reply' => FounderHandoff::invalidMessage('email')])
            ->assertJsonPath('errors.contact', 'Cette adresse e-mail n\'est pas complète. Exemple : prenom@gmail.com.');

        $this->submit(['channel' => 'whatsapp', 'contact' => '91 23 45 67'])
            ->assertJson(['ok' => false, 'reply' => FounderHandoff::invalidMessage('whatsapp')])
            ->assertJsonPath('errors.contact', 'Ce numéro WhatsApp n\'est pas complet : ajoutez l\'indicatif du pays. Exemple : +228 91 23 45 67.');

        $this->submit(['first_name' => '', 'channel' => 'sms', 'question' => ''])
            ->assertJson(['ok' => false])
            ->assertJsonStructure(['errors' => ['first_name', 'channel', 'question']]);

        $this->assertSame(0, AssistantHandoff::query()->count());
        Mail::assertNothingSent();
    }

    public function test_codes_and_passwords_are_never_kept_in_the_question(): void
    {
        $this->submit(['question' => 'Mon mot de passe: Soleil2026 ne marche plus, et mon code secret 4827 non plus.'])->assertJsonPath('ok', true);

        $question = AssistantHandoff::query()->sole()->question;
        $this->assertStringNotContainsString('Soleil2026', $question);
        $this->assertStringNotContainsString('4827', $question);
    }

    public function test_three_requests_at_most_per_visitor_and_per_day(): void
    {
        foreach (range(1, 3) as $index) {
            $this->submit(['question' => 'Question numéro '.$index])->assertJsonPath('ok', true);
        }

        $this->submit(['question' => 'Une quatrième question'])
            ->assertOk()
            ->assertJson(['ok' => false, 'limit' => true, 'reply' => FounderHandoff::limitMessage()])
            ->assertJsonPath('whatsapp', fn (string $url): bool => str_starts_with($url, 'https://wa.me/22872240231'));
        $this->assertSame(3, AssistantHandoff::query()->count());

        // Un autre visiteur n'est pas concerné.
        $this->submit(['question' => 'Une autre personne'], str_repeat('d', 40))->assertJsonPath('ok', true);

        // Le lendemain (journée de Lomé), c'est de nouveau possible.
        $this->travel(1)->days();
        $this->submit(['question' => 'Le lendemain'])->assertJsonPath('ok', true);
    }

    public function test_robots_filling_the_trap_field_send_nothing(): void
    {
        $this->submit(['website' => 'https://spam.example'])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(0, AssistantHandoff::query()->count());
        Mail::assertNothingSent();
    }

    public function test_the_form_works_even_when_gemini_is_unavailable(): void
    {
        // Limite du site atteinte : Ama ne répond plus, la relève (WhatsApp et formulaire) reste possible.
        AssistantUsage::query()->create(['day' => AssistantUsage::today(), 'requests' => 400, 'failures' => 0]);
        $this->ask('Bonjour')->assertJson(['fallback' => true, 'handoff' => null]);

        $this->submit(['source' => 'form'])->assertJsonPath('ok', true);
        $this->assertSame('form', AssistantHandoff::query()->sole()->source);
    }

    public function test_nothing_is_sent_when_ama_is_disabled(): void
    {
        SiteSettings::save(['site.voice_assistant' => ['enabled' => false, 'provider' => 'gemini']]);

        $this->submit()->assertJsonPath('ok', false);
        $this->assertSame(0, AssistantHandoff::query()->count());
    }

    public function test_the_call_mode_never_speaks_the_technical_line_and_shows_the_card_at_the_end(): void
    {
        $body = '';
        foreach (['Très bien Awa. Une petite fiche ', 's\'affichera à la fin de l\'appel pour écrire votre adresse. ', "\n[TRANSM", 'ETTRE] {"prenom": "Awa", "canal": "email", ', '"contact": "", "question": "Proposez-vous une formation en comptabilité ?"}'] as $chunk) {
            $body .= 'data: '.json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $chunk]]]]]])."\r\n\r\n";
        }
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->withCredentials()->withCookie('ama_visitor', self::VISITOR)
            ->post(route('assistant.call'), ['message' => 'Oui, transmettez ma question, par e-mail. Je m\'appelle Awa.'], ['Accept' => 'application/x-ndjson'])
            ->assertOk();
        $events = array_map(fn (string $line): array => json_decode($line, true), array_values(array_filter(explode("\n", $response->streamedContent()))));
        $done = array_pop($events);
        $spoken = implode('', array_column($events, 'text'));

        $this->assertStringNotContainsString('TRANSM', $spoken);
        $this->assertStringNotContainsString('{', $spoken);
        $this->assertSame('Très bien Awa. Une petite fiche s\'affichera à la fin de l\'appel pour écrire votre adresse.', $done['reply']);
        $this->assertSame(['first_name' => 'Awa', 'channel' => 'email', 'contact' => '', 'question' => 'Proposez-vous une formation en comptabilité ?'], $done['handoff']);
        $this->assertSame(0, AssistantHandoff::query()->count());
    }

    public function test_the_card_and_the_form_are_on_the_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-test="ama-handoff"', false)
            ->assertSee('Transmettre à Coach Joseph')
            ->assertSee('Envoyer à Coach Joseph')
            ->assertSee('Corriger')
            ->assertSee('Transmettre ma question à Coach Joseph')
            ->assertSee('id="ama-handoff-website"', false)
            ->assertSee('handoffEndpoint', false);
    }

    public function test_the_privacy_policy_explains_the_handoff(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('data-test="privacy-handoff"', false)
            ->assertSee('Transmettre une question au fondateur.')
            ->assertSee('Votre adresse e-mail et votre numéro ne sont jamais transmis à Gemini')
            ->assertSee('conservées 90 jours');
    }

    public function test_the_admin_lists_and_handles_the_requests(): void
    {
        $this->submit(['question' => 'Quand commence la prochaine session ?']);
        $this->submit(['first_name' => 'Kofi', 'channel' => 'whatsapp', 'contact' => '+228 91 23 45 67', 'question' => 'Avez-vous une formation le samedi ?'], str_repeat('e', 40));

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->assertSame(2, AdminMenu::todoTotal($admin));
        $this->assertStringContainsString('Demandes transmises', collect(AdminMenu::groups($admin))->flatMap(fn (array $group) => array_column($group['links'], 'label'))->implode('|'));

        $this->actingAs($admin)->get(route('admin.assistant.handoffs.index'))
            ->assertOk()
            ->assertSee('Demandes transmises')
            ->assertSee('À traiter (2)')
            ->assertSee('Quand commence la prochaine session ?')
            ->assertSee('Par e-mail : awa.kodjo@example.com')
            ->assertSee('Sur WhatsApp : +228 91 23 45 67')
            ->assertSee('https://wa.me/22891234567?text=', false)
            ->assertSee('Répondre par e-mail')
            ->assertSee('Marquer comme traitée');

        $kofi = AssistantHandoff::query()->where('first_name', 'Kofi')->sole();
        $this->actingAs($admin)->post(route('admin.assistant.handoffs.toggle', $kofi))->assertRedirect()->assertSessionHas('status', 'Demande de Kofi marquée comme traitée.');

        $kofi->refresh();
        $this->assertFalse($kofi->isPending());
        $this->assertSame($admin->id, $kofi->handled_by);
        $this->assertNotNull($kofi->handled_at);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::HandoffHandled)->exists());

        $this->actingAs($admin)->get(route('admin.assistant.handoffs.index'))->assertSee('À traiter (1)')->assertDontSee('Avez-vous une formation le samedi ?');
        $this->actingAs($admin)->get(route('admin.assistant.handoffs.index', ['filtre' => 'traitees']))->assertSee('Avez-vous une formation le samedi ?')->assertSee('Remettre à traiter');

        $this->actingAs($admin)->post(route('admin.assistant.handoffs.toggle', $kofi));
        $this->assertTrue($kofi->refresh()->isPending());
        $this->assertNull($kofi->handled_by);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::HandoffReopened)->exists());

        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Questions transmises par Ama');
    }

    public function test_moderators_and_visitors_cannot_see_the_requests(): void
    {
        $this->submit();
        $handoff = AssistantHandoff::query()->sole();

        $this->get(route('admin.assistant.handoffs.index'))->assertRedirect(route('login'));

        foreach ([User::factory()->create(['role' => UserRole::Moderator]), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.assistant.handoffs.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.assistant.handoffs.toggle', $handoff))->assertForbidden();
        }

        $this->assertTrue($handoff->refresh()->isPending());
    }

    public function test_requests_are_erased_after_90_days(): void
    {
        $this->submit(['question' => 'Ancienne question']);
        $this->travel(91)->days();
        $this->submit(['question' => 'Nouvelle question']);

        $this->artisan('model:prune', ['--model' => [AssistantConversation::class, AssistantHandoff::class]])->assertSuccessful();

        $this->assertSame(['Nouvelle question'], AssistantHandoff::query()->pluck('question')->all());
    }

    public function test_the_card_functions_in_the_browser(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/ama.js.');
        }

        $module = json_encode('file://'.resource_path('js/ama.js'));
        $program = <<<JS
            const { handoffFromDraft, handoffErrors, handoffIsReady, lastVisitorQuestion } = await import({$module});
            const messages = [{ from: 'visitor', text: 'Première question' }, { from: 'ama', text: 'Réponse' }, { from: 'visitor', text: ' Avez-vous une formation le samedi ? ' }, { from: 'ama', text: 'Je n\'ai pas cette information.' }];
            const ready = handoffFromDraft({ first_name: 'Awa', channel: 'email', contact: 'awa@example.com', question: 'Question ?' }, { source: 'chat' });
            const call = handoffFromDraft({ first_name: 'Awa', channel: 'whatsapp', contact: '', question: '' }, { source: 'call', question: lastVisitorQuestion(messages) });
            const form = handoffFromDraft({}, { source: 'autre', question: lastVisitorQuestion(messages) });
            console.log(JSON.stringify({
                last: lastVisitorQuestion(messages),
                ready: handoffIsReady(ready),
                call, callErrors: handoffErrors(call), callReady: handoffIsReady(call),
                form,
                emailWrong: handoffErrors({ ...ready, contact: 'awa@gmail' }),
                phoneLocal: handoffErrors({ ...ready, channel: 'whatsapp', contact: '91 23 45 67' }),
                phoneOk: handoffErrors({ ...ready, channel: 'whatsapp', contact: '+228 91 23 45 67' }),
                phone00: handoffErrors({ ...ready, channel: 'whatsapp', contact: '00229 01 94 55 59 14' }),
            }));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame('Avez-vous une formation le samedi ?', $out['last']);
        $this->assertTrue($out['ready']);
        // Fin d'un appel : la question est reprise, la coordonnée reste à écrire (la carte s'ouvre en mode « Corriger »).
        $this->assertSame(['first_name' => 'Awa', 'channel' => 'whatsapp', 'contact' => '', 'question' => 'Avez-vous une formation le samedi ?', 'source' => 'call'], $out['call']);
        $this->assertSame(['contact'], array_keys($out['callErrors']));
        $this->assertFalse($out['callReady']);
        $this->assertSame('form', $out['form']['source']);
        $this->assertSame('Avez-vous une formation le samedi ?', $out['form']['question']);
        $this->assertSame(['contact' => 'Cette adresse e-mail n\'est pas complète. Exemple : prenom@gmail.com.'], $out['emailWrong']);
        $this->assertSame(['contact' => 'Ce numéro WhatsApp n\'est pas complet : ajoutez l\'indicatif du pays. Exemple : +228 91 23 45 67.'], $out['phoneLocal']);
        $this->assertSame([], $out['phoneOk']);
        $this->assertSame([], $out['phone00']);
    }
}
