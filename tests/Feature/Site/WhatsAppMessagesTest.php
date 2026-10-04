<?php

namespace Tests\Feature\Site;

use App\Enums\AdminAction;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\LiveSession;
use App\Models\Purchase;
use App\Models\User;
use App\Support\WhatsApp;
use App\Support\WhatsAppMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Boutons WhatsApp : un message prérempli adapté à la page, que la personne complète elle-même. Chaque message
 * commence par « Bonjour Coach Joseph » et se termine par « Ma question : » (ou « Mon problème : »), sans
 * parenthèse ni texte à effacer. Modèles modifiables dans l'admin (Réglages du site > Messages WhatsApp).
 */
class WhatsAppMessagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Messages (décodés) de tous les liens WhatsApp de l'école présents dans la page.
     *
     * @return list<string>
     */
    private function messages(TestResponse $response): array
    {
        preg_match_all('#https://wa\.me/'.config('site.whatsapp.number').'\?text=([^"\'\s<>]+)#', (string) $response->getContent(), $matches);

        return array_values(array_unique(array_map(fn (string $text): string => rawurldecode(html_entity_decode($text)), $matches[1])));
    }

    private function assertEveryMessageIsPersonal(TestResponse $response, string $expected): void
    {
        $messages = $this->messages($response);

        $this->assertNotEmpty($messages);
        $this->assertSame([$expected], $messages, 'Tous les boutons WhatsApp de la page doivent avoir le même message adapté.');
        $this->assertStringStartsWith('Bonjour Coach Joseph', $expected);
        $this->assertMatchesRegularExpression('/(Ma question|Mon problème) : $/u', $expected);
        $this->assertStringNotContainsString('Lomé IA School, je souhaite avoir', $expected);
    }

    public function test_other_pages_use_the_general_message(): void
    {
        $general = 'Bonjour Coach Joseph, je suis sur le site Lomé IA School. Ma question : ';
        $this->assertSame($general, WhatsAppMessages::general());
        $this->assertSame('https://wa.me/22872240231?text='.rawurlencode($general), WhatsApp::url());

        foreach (['home', 'formations.index', 'about', 'contact', 'business'] as $route) {
            $this->assertEveryMessageIsPersonal($this->get(route($route))->assertOk(), $general);
        }
    }

    public function test_formation_page_names_the_formation(): void
    {
        $formation = Formation::factory()->create(['title' => 'Agents IA', 'price' => 25000]);

        $this->assertEveryMessageIsPersonal(
            $this->get(route('formations.show', $formation))->assertOk(),
            'Bonjour Coach Joseph, je suis sur la page de la formation « Agents IA ». Ma question : ',
        );
    }

    public function test_checkout_names_the_formation_and_the_step(): void
    {
        $formation = Formation::factory()->create(['title' => 'ChatGPT au quotidien', 'price' => 15000]);
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get(route('checkout.show', $formation))->assertOk();
        $messages = $this->messages($response);

        // En-tête et « Besoin d'aide pour payer ? » : l'étape affichée au chargement (choix du pays).
        $this->assertContains('Bonjour Coach Joseph, j\'ai besoin d\'aide pour payer la formation « ChatGPT au quotidien » (étape : choix du pays). Mon problème : ', $messages);
        foreach ($messages as $message) {
            $this->assertStringStartsWith('Bonjour Coach Joseph, j\'ai besoin d\'aide pour payer la formation « ChatGPT au quotidien »', $message);
            $this->assertStringEndsWith('Mon problème : ', $message);
        }

        // Adresse e-mail pas encore confirmée : l'étape est la confirmation de l'adresse.
        $unconfirmed = User::factory()->create(['email_verified_at' => null]);
        $this->assertSame(
            ['Bonjour Coach Joseph, j\'ai besoin d\'aide pour payer la formation « ChatGPT au quotidien » (étape : confirmation de l\'adresse e-mail). Mon problème : '],
            $this->messages($this->actingAs($unconfirmed)->get(route('checkout.show', $formation))->assertOk()),
        );
    }

    public function test_session_supplement_message_names_the_session(): void
    {
        $session = LiveSession::factory()->create();
        $text = WhatsAppMessages::payment($session->formation, $session, 'envoi de la capture');

        $this->assertStringStartsWith('Bonjour Coach Joseph, j\'ai besoin d\'aide pour payer le supplément de la session du ', $text);
        $this->assertStringContainsString('« '.$session->formation->title.' » (étape : envoi de la capture). Mon problème : ', $text);
    }

    public function test_free_formation_and_its_full_screen(): void
    {
        $formation = Formation::factory()->create(['title' => 'Formation gratuite : utiliser l\'IA', 'price' => 0, 'event_date' => '2026-10-10']);
        $option = $formation->freeOptions()->create(['label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 1, 'position' => 1]);

        $this->assertEveryMessageIsPersonal(
            $this->get(route('formations.show', $formation))->assertOk(),
            'Bonjour Coach Joseph, j\'ai une question sur la formation gratuite du 10 octobre. Ma question : ',
        );

        // Tout est complet : le bouton de l'écran « Complet » a son propre message.
        FreeRegistration::query()->create(['formation_id' => $formation->id, 'user_id' => User::factory()->create()->id, 'free_registration_option_id' => $option->id]);
        $messages = $this->messages($this->get(route('formations.show', $formation))->assertOk()->assertSee('data-test="free-full"', false));

        $this->assertContains('Bonjour Coach Joseph, la formation gratuite du 10 octobre est complète. Ma question : ', $messages);
        $this->assertContains('Bonjour Coach Joseph, j\'ai une question sur la formation gratuite du 10 octobre. Ma question : ', $messages);
    }

    public function test_free_formation_without_date_uses_its_name(): void
    {
        $formation = Formation::factory()->create(['title' => 'Atelier IA', 'price' => 0, 'event_date' => null]);

        $this->assertSame('Bonjour Coach Joseph, j\'ai une question sur la formation gratuite « Atelier IA ». Ma question : ', WhatsAppMessages::free($formation));
    }

    public function test_consultation_and_refused_payment_messages(): void
    {
        $this->assertEveryMessageIsPersonal($this->get(route('consultation'))->assertOk(), 'Bonjour Coach Joseph, j\'ai une question sur la consultation gratuite. Ma question : ');

        $student = User::factory()->create();
        $formation = Formation::factory()->create(['title' => 'Création visuelle']);
        $purchase = Purchase::factory()->for($student)->for($formation)->status(PurchaseStatus::Rejected)->create();

        $messages = $this->messages($this->actingAs($student)->get(route('student.purchases.show', $purchase))->assertOk());
        $this->assertSame(['Bonjour Coach Joseph, mon paiement '.$purchase->receipt_number.' pour « Création visuelle » a été refusé. Ma question : '], $messages);
    }

    public function test_admin_edits_the_templates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.settings.edit', 'messages-whatsapp'))
            ->assertOk()
            ->assertSee('Messages WhatsApp')
            ->assertSee('data-test="whatsapp-template-free_full"', false)
            ->assertSee('Ma question :');

        $messages = WhatsAppMessages::TEMPLATES;
        $form = array_map(fn () => '', $messages);
        $form['formation'] = 'Bonjour Coach Joseph, une question sur « {formation} ». Ma question :';

        $this->actingAs($admin)->put(route('admin.settings.update', 'messages-whatsapp'), ['messages' => $form])->assertSessionHasNoErrors();

        $formation = Formation::factory()->create(['title' => 'Agents IA']);
        $this->assertSame('Bonjour Coach Joseph, une question sur « Agents IA ». Ma question : ', WhatsAppMessages::formation($formation));
        // Champ vidé : texte d'origine.
        $this->assertSame('Bonjour Coach Joseph, je suis sur le site Lomé IA School. Ma question : ', WhatsAppMessages::general());
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'Messages WhatsApp modifiés.')->exists());
    }

    public function test_templates_must_end_with_the_question_and_use_known_placeholders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->put(route('admin.settings.update', 'messages-whatsapp'), ['messages' => ['general' => 'Bonjour, (écrivez votre question ici)']])
            ->assertSessionHasErrors('messages.general');

        $this->actingAs($admin)->put(route('admin.settings.update', 'messages-whatsapp'), ['messages' => ['general' => 'Bonjour {formation}. Ma question :']])
            ->assertSessionHasErrors('messages.general');

        $this->assertSame('Bonjour Coach Joseph, je suis sur le site Lomé IA School. Ma question : ', WhatsAppMessages::general());
    }

    public function test_moderators_cannot_edit_the_templates(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->put(route('admin.settings.update', 'messages-whatsapp'), ['messages' => ['general' => 'Coucou :']])->assertForbidden();
    }
}
