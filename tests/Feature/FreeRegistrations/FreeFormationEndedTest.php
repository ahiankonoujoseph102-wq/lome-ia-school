<?php

namespace Tests\Feature\FreeRegistrations;

use App\Assistant\AmaKnowledge;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\FreeRegistrations\FreeRegistrar;
use App\Models\AdminActivity;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\InternalPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Formation gratuite terminée (interrupteur « Formation terminée » de l'admin) : la page reste en ligne, ne propose
 * plus de s'inscrire et affiche un bouton vers l'accompagnement ; le serveur refuse toute inscription et toute
 * annulation ; les inscrits et le groupe des inscrits ne changent pas.
 */
class FreeFormationEndedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-10-08 10:00', 'Africa/Lome'));
    }

    private function freeFormation(bool $ended = false, array $attributes = []): Formation
    {
        $formation = Formation::factory()->create([
            'title' => 'Formation gratuite exemple',
            'subtitle' => 'Sous-titre de la formation gratuite',
            'price' => 0,
            'event_date' => '2026-10-10',
            'event_location' => 'Adidogomé, Lycée moderne 2, Lomé',
            ...$attributes,
        ]);

        $formation->freeOptions()->create(['label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 20, 'position' => 1]);
        $formation->freeOptions()->create(['label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50, 'position' => 2]);

        if ($ended) {
            $formation->forceFill(['free_ended' => true])->save();
        }

        return $formation;
    }

    private function publishPack(): Formation
    {
        return Formation::factory()->pack()->create(['title' => 'Accompagnement IA exemple', 'price' => 10000, 'is_active' => true]);
    }

    /**
     * Personne inscrite (option « En ligne soir ») avant que la formation soit marquée terminée.
     */
    private function registeredStudent(Formation $formation): User
    {
        $student = User::factory()->create(['name' => 'Kossi Exemple']);
        app(FreeRegistrar::class)->register($student, $formation, $formation->freeOptions()->where('label', 'En ligne soir')->value('id'));

        return $student;
    }

    private function inGroup(User $user, Formation $formation): bool
    {
        return ChatGroup::forFormation($formation)->members()->where('user_id', $user->id)->whereNull('removed_at')->exists();
    }

    private function saveSettings(Formation $formation, array $data): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.free-registrations.ended', $formation), $data);
    }

    public function test_the_switch_is_off_by_default_and_the_page_is_unchanged(): void
    {
        $this->publishPack();
        $formation = $this->freeFormation();

        $this->assertFalse($formation->fresh()->free_ended);
        $this->assertFalse($formation->fresh()->isFreeEnded());

        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('Formation gratuite')
            ->assertSee('data-test="free-price"', false)
            ->assertSee('Je m\'inscris gratuitement')
            ->assertSee('data-test="free-options-info"', false)
            ->assertSee('data-test="free-bar-register"', false)
            ->assertDontSee('Formation terminée')
            ->assertDontSee('data-test="free-ended-button"', false)
            ->assertDontSee('Découvrir l\'accompagnement');
    }

    public function test_a_visitor_sees_an_ended_formation_with_the_accompaniment_button(): void
    {
        $this->publishPack();
        $formation = $this->freeFormation(ended: true, attributes: [
            'presentation_video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'presentation_video_enabled' => true,
        ]);

        $response = $this->get(route('formations.show', $formation))->assertOk();
        $html = (string) $response->getContent();

        $response
            // Gardés : titre, sous-titre, date, lieu, présentation, « Poser une question », « Partager cette formation ».
            ->assertSee('Formation gratuite exemple')
            ->assertSee('Sous-titre de la formation gratuite')
            ->assertSee('Samedi 10 octobre 2026')
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé')
            ->assertSee('Présentation')
            ->assertSee('Poser une question')
            ->assertSee('Partager cette formation')
            // Pastille et barre du bas.
            ->assertSee('data-test="free-eyebrow"', false)
            ->assertSee('Formation terminée')
            ->assertSee('data-test="free-bar-ended"', false)
            ->assertSee('La vidéo de présentation')
            ->assertDontSee('Coach Joseph vous présente la formation')
            // Masqués : prix, inscription, options, places, annulation.
            ->assertDontSee('data-test="free-price"', false)
            ->assertDontSee('data-test="free-hero-register"', false)
            ->assertDontSee('data-test="free-bar-register"', false)
            ->assertDontSee('Je m\'inscris')
            ->assertDontSee('data-test="free-options-info"', false)
            ->assertDontSee('data-test="free-seats"', false)
            ->assertDontSee('places restantes')
            ->assertDontSee('data-test="free-create-account"', false)
            ->assertDontSee('data-test="free-video-action"', false)
            ->assertDontSee('data-test="free-cancel"', false)
            ->assertDontSee('Vous ne pouvez plus venir')
            ->assertDontSee('data-test="free-steps"', false);

        // Le grand bouton doré, vers la page de l'accompagnement, en haut et dans la barre du bas.
        $this->assertSame(1, preg_match('/<a href="([^"]+)" class="btn-gold[^"]*" data-test="free-ended-button"[^>]*>\s*Découvrir l&#039;accompagnement/', $html, $match));
        $this->assertSame(route('packs'), $match[1]);
        $this->assertStringContainsString('>Découvrir l&#039;accompagnement</a>', $html);
        $this->assertStringNotContainsString('>Gratuit<', $html);
    }

    public function test_a_registered_person_keeps_the_group_and_sees_a_past_tense_message(): void
    {
        $this->publishPack();
        $formation = $this->freeFormation();
        $student = $this->registeredStudent($formation);
        $formation->forceFill(['free_ended' => true])->save();

        $this->actingAs($student)->get(route('formations.show', $formation))->assertOk()
            ->assertSee('Vous avez participé à cette formation')
            ->assertSee('Le lien Google Meet a été publié dans le groupe des inscrits.')
            ->assertSee('data-test="free-registered"', false)
            ->assertSee('En ligne soir')
            ->assertSee('data-test="free-open-group"', false)
            ->assertSee(route('chat.show', $formation), false)
            ->assertSee('data-test="free-ended-button"', false)
            ->assertSee('data-test="free-bar-ended"', false)
            ->assertDontSee('Vous êtes inscrit(e)')
            ->assertDontSee('data-test="free-cancel"', false)
            ->assertDontSee('Annuler mon inscription')
            ->assertDontSee('Vous ne pouvez plus venir')
            ->assertDontSee('Voir mon inscription')
            ->assertDontSee('Mon inscription');

        // Le groupe des inscrits reste ouvert à cette personne.
        $this->actingAs($student)->get(route('chat.show', $formation))->assertOk();
    }

    public function test_an_admin_sees_the_ended_page_and_can_switch_it_on_and_off(): void
    {
        $this->publishPack();
        $formation = $this->freeFormation();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.free-registrations.show', $formation))->assertOk()
            ->assertSee('data-test="free-ended-settings"', false)
            ->assertSee('data-test="free-ended-toggle"', false)
            ->assertSee('Texte du bouton')
            ->assertSee('Destination du bouton');
        $this->actingAs($admin)->get(route('admin.formations.edit', $formation))->assertOk()->assertSee('data-test="free-ended-link"', false);

        $this->actingAs($admin)->put(route('admin.free-registrations.ended', $formation), ['free_ended' => '1', 'free_ended_button_label' => '', 'free_ended_button_url' => ''])
            ->assertRedirect(route('admin.free-registrations.show', $formation))
            ->assertSessionHasNoErrors();

        $formation->refresh();
        $this->assertTrue($formation->isFreeEnded());
        $this->assertNull($formation->free_ended_button_label);
        $this->assertNull($formation->free_ended_button_url);
        $this->assertSame('Découvrir l\'accompagnement', $formation->endedButtonLabel());
        $this->assertSame('/accompagnement', $formation->endedButtonPath());
        $this->assertTrue(AdminActivity::query()->where('description', 'like', 'Formation terminée : %')->exists());

        $this->actingAs($admin)->get(route('formations.show', $formation))->assertOk()
            ->assertSee('Formation terminée')
            ->assertSee('data-test="free-ended-button"', false)
            ->assertDontSee('Je m\'inscris gratuitement');

        // Texte et destination personnalisés (adresse interne, même saisie sans barre ou avec l'adresse complète du site).
        $this->actingAs($admin)->put(route('admin.free-registrations.ended', $formation), ['free_ended' => '1', 'free_ended_button_label' => 'Voir les formations', 'free_ended_button_url' => ' formations '])
            ->assertSessionHasNoErrors();
        $formation->refresh();
        $this->assertSame('Voir les formations', $formation->endedButtonLabel());
        $this->assertSame('/formations', $formation->free_ended_button_url);
        $this->assertSame(['label' => 'Voir les formations', 'url' => url('/formations')], $formation->endedButton());

        $this->actingAs($admin)->put(route('admin.free-registrations.ended', $formation), ['free_ended' => '1', 'free_ended_button_url' => rtrim((string) config('app.url'), '/').'/accompagnement'])
            ->assertSessionHasNoErrors();
        $this->assertNull($formation->fresh()->free_ended_button_url, 'L\'adresse par défaut n\'est pas enregistrée.');

        // Éteint : la page redevient exactement celle d'avant.
        $this->actingAs($admin)->put(route('admin.free-registrations.ended', $formation), ['free_ended' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($formation->fresh()->isFreeEnded());
        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('Je m\'inscris gratuitement')
            ->assertDontSee('Formation terminée');
    }

    public function test_only_the_admin_can_change_it_and_paid_formations_cannot_be_set(): void
    {
        $formation = $this->freeFormation();
        $data = ['free_ended' => '1'];

        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]))->put(route('admin.free-registrations.ended', $formation), $data)->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('admin.free-registrations.ended', $formation), $data)->assertForbidden();
        $this->assertFalse($formation->fresh()->free_ended);

        $paid = Formation::factory()->create(['price' => 15000]);
        $this->saveSettings($paid, $data)->assertNotFound();

        // Une formation payante n'est jamais « terminée », même si la colonne était allumée.
        $paid->forceFill(['free_ended' => true])->save();
        $this->assertFalse($paid->fresh()->isFreeEnded());
        $this->get(route('formations.show', $paid))->assertOk()->assertDontSee('Formation terminée');
    }

    public function test_an_external_destination_is_refused(): void
    {
        $formation = $this->freeFormation();

        foreach (['https://exemple-arnaque.com/accompagnement', 'http://evil.example/x', '//evil.example/x', 'javascript:alert(1)', 'mailto:a@b.c', 'tel:+22890000000', '/\\evil.example', 'www.evil.example/page'] as $url) {
            $this->saveSettings($formation, ['free_ended' => '1', 'free_ended_button_url' => $url])
                ->assertSessionHasErrors('free_ended_button_url');
            $this->assertFalse($formation->fresh()->free_ended, $url);
            $this->assertNull($formation->fresh()->free_ended_button_url, $url);
        }

        // Une valeur externe déjà enregistrée (base modifiée à la main) n'est jamais utilisée.
        $formation->forceFill(['free_ended' => true, 'free_ended_button_url' => 'https://evil.example'])->save();
        $this->assertSame('/accompagnement', $formation->fresh()->endedButtonPath());
    }

    public function test_internal_paths_are_cleaned(): void
    {
        $host = rtrim((string) config('app.url'), '/');

        $this->assertSame('/accompagnement', InternalPath::clean('/accompagnement'));
        $this->assertSame('/accompagnement', InternalPath::clean("accompagnement\u{200B}"));
        $this->assertSame('/formations?categorie=ia#catalogue', InternalPath::clean('/formations?categorie=ia#catalogue'));
        $this->assertSame('/accompagnement', InternalPath::clean($host.'/accompagnement'));
        $this->assertSame('/', InternalPath::clean($host));
        $this->assertNull(InternalPath::clean('https://evil.example/accompagnement'));
        $this->assertNull(InternalPath::clean('//evil.example'));
        $this->assertNull(InternalPath::clean('JavaScript:alert(1)'));
        $this->assertNull(InternalPath::clean('/a b'));
        $this->assertNull(InternalPath::clean(''));
        $this->assertSame('/accompagnement', InternalPath::route('/accompagnement/?ref=X#a'));
    }

    public function test_the_button_is_hidden_when_no_offer_is_on_sale(): void
    {
        // Les trois offres créées par la migration sont masquées : aucune offre en vente.
        $formation = $this->freeFormation(ended: true);
        $this->get(route('packs'))->assertRedirect(route('formations.index'));

        $this->assertNull($formation->endedButton());
        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('Formation terminée')
            ->assertSee('data-test="free-ended"', false)
            ->assertDontSee('data-test="free-ended-button"', false)
            ->assertDontSee('data-test="free-bar-ended"', false)
            ->assertDontSee('Découvrir l\'accompagnement')
            ->assertDontSee('Je m\'inscris');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.free-registrations.show', $formation))
            ->assertOk()->assertSee('data-test="free-ended-hidden-button"', false);

        // Une autre destination interne reste affichée, même sans offre en vente.
        $formation->forceFill(['free_ended_button_url' => '/formations', 'free_ended_button_label' => 'Voir les formations'])->save();
        auth()->logout();
        $this->get(route('formations.show', $formation))->assertOk()->assertSee('data-test="free-ended-button"', false)->assertSee('Voir les formations');

        // Offre publiée : le bouton vers l'accompagnement revient.
        $formation->forceFill(['free_ended_button_url' => null, 'free_ended_button_label' => null])->save();
        $this->publishPack();
        $this->get(route('formations.show', $formation))->assertOk()->assertSee('data-test="free-ended-button"', false);
    }

    public function test_the_server_refuses_any_new_registration(): void
    {
        $formation = $this->freeFormation(ended: true);
        $student = User::factory()->create();
        $optionId = $formation->freeOptions()->value('id');
        $page = route('formations.show', $formation).'#inscription';

        // Page restée ouverte : formulaire envoyé quand même.
        $this->actingAs($student)->post(route('free-registration.store', $formation), ['option' => $optionId])
            ->assertRedirect($page)
            ->assertSessionHasErrors(['option' => FreeRegistrar::ENDED_REGISTER]);

        // Anciens liens : création du compte, choix de l'option, ancien parcours « option d'abord ».
        $this->actingAs($student)->get(route('free-registration.start', $formation))->assertRedirect($page);
        $this->actingAs($student)->get(route('free-registration.options', $formation))->assertRedirect($page);
        auth()->logout();
        $this->post(route('free-registration.choose', $formation), ['option' => $optionId])
            ->assertRedirect($page)
            ->assertSessionHasErrors(['option' => FreeRegistrar::ENDED_REGISTER]);

        $this->assertSame(0, FreeRegistration::query()->where('formation_id', $formation->id)->count());
        $this->assertFalse($student->enrollments()->where('formation_id', $formation->id)->exists());

        // Le message s'affiche clairement sur la page après le refus.
        $this->actingAs($student)->followingRedirects()
            ->post(route('free-registration.store', $formation), ['option' => $optionId])
            ->assertOk()
            ->assertSee('data-test="free-ended-error"', false)
            ->assertSee('Cette formation est terminée : les inscriptions sont fermées.');

        // Même par le service directement.
        $this->expectException(ValidationException::class);
        app(FreeRegistrar::class)->register($student, $formation, $optionId);
    }

    public function test_the_server_refuses_any_cancellation_and_nothing_changes(): void
    {
        $formation = $this->freeFormation();
        $student = $this->registeredStudent($formation);
        $formation->forceFill(['free_ended' => true])->save();

        $this->assertTrue($this->inGroup($student, $formation));

        $this->actingAs($student)->delete(route('free-registration.destroy', $formation))
            ->assertRedirect(route('formations.show', $formation).'#inscription')
            ->assertSessionHasErrors(['option' => FreeRegistrar::ENDED_CANCEL]);

        $registration = FreeRegistration::query()->where('formation_id', $formation->id)->where('user_id', $student->id)->sole();
        $this->assertNull($registration->cancelled_at);
        $this->assertTrue($student->enrollments()->where('formation_id', $formation->id)->where('source', EnrollmentSource::Free->value)->exists());
        $this->assertTrue($this->inGroup($student, $formation));

        try {
            app(FreeRegistrar::class)->cancel($student, $formation);
            $this->fail('L\'annulation aurait dû être refusée.');
        } catch (ValidationException $exception) {
            $this->assertSame(FreeRegistrar::ENDED_CANCEL, $exception->validator->errors()->first('option'));
        }

        $this->assertNull($registration->fresh()->cancelled_at);
        $this->assertTrue($this->inGroup($student, $formation));
    }

    public function test_the_menu_link_appears_only_when_an_offer_is_published(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="menu-packs"', false);

        $pack = $this->publishPack();

        $response = $this->get(route('home'))->assertOk()->assertSee('data-test="menu-packs"', false);
        $this->assertSame(1, preg_match('/<a\s+href="([^"]+)"[^>]*data-test="menu-packs"[^>]*>Accompagnement<\/a>/s', (string) $response->getContent(), $match));
        $this->assertSame(route('packs'), $match[1]);

        // Lien aussi dans le menu du téléphone, et page active mise en avant.
        $this->get(route('packs'))->assertOk()->assertSee('aria-current="page"', false);
        $this->assertGreaterThanOrEqual(2, substr_count((string) $this->get(route('about'))->getContent(), 'href="'.route('packs').'"'));

        $pack->forceFill(['is_active' => false])->save();
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="menu-packs"', false);
    }

    public function test_ama_never_offers_to_register_to_an_ended_formation(): void
    {
        Http::fake();
        config(['services.gemini.key' => 'cle-de-test', 'services.gemini.model' => 'modele-de-test']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);

        $formation = $this->freeFormation(ended: true);

        // Aucune offre en vente : bulle habituelle d'une formation, jamais « Envie de vous inscrire ? ».
        $config = $this->browserConfig($this->get(route('formations.show', $formation)));
        $this->assertSame('formation', $config['nudge']['type']);
        $this->assertStringNotContainsString('inscri', $config['nudge']['text']);
        $this->assertStringContainsString('TERMINÉE', $config['context']['role_page']);
        $this->assertStringContainsString('ne propose jamais de s\'y inscrire', $config['context']['role_page']);
        $this->assertStringNotContainsString('Je m\'inscris', $config['context']['role_page']);

        // Offre en vente : la bulle et le contexte orientent vers l'accompagnement.
        $this->publishPack();
        $config = $this->browserConfig($this->get(route('formations.show', $formation)));
        $this->assertSame('ended', $config['nudge']['type']);
        $this->assertStringContainsString('accompagnement', $config['nudge']['text']);
        $this->assertStringContainsString('« Découvrir l\'accompagnement » (/accompagnement)', $config['context']['role_page']);

        $knowledge = AmaKnowledge::freeText($formation);
        $this->assertStringContainsString('FORMATION GRATUITE TERMINÉE', $knowledge);
        $this->assertStringNotContainsString('créer son compte', $knowledge);
        $this->assertStringNotContainsString('places restantes', $knowledge);

        // Formation gratuite encore ouverte : rien ne change.
        $open = $this->freeFormation();
        $this->assertSame('free', $this->browserConfig($this->get(route('formations.show', $open)))['nudge']['type']);
    }

    public function test_the_migrations_end_the_october_formation_and_can_run_again(): void
    {
        // Structure : reprise sans erreur si les colonnes existent déjà.
        $structure = require database_path('migrations/2026_10_22_100000_add_ended_state_to_formations.php');
        $structure->up();
        $this->assertTrue(Schema::hasColumns('formations', ['free_ended', 'free_ended_button_label', 'free_ended_button_url']));

        // La formation du 10 octobre est terminée, sans toucher à ses options ni à son groupe.
        $october = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        $this->assertTrue($october->isFreeEnded());
        $this->assertSame(4, $october->freeOptions()->count());
        $this->assertNotNull(ChatGroup::query()->where('formation_id', $october->id)->first());

        // Inscription existante gardée, et relancer la migration ne change rien (ni un interrupteur éteint dans l'admin).
        $october->forceFill(['is_active' => true, 'free_ended' => false])->save();
        $student = $this->registeredStudent($october);
        $october->forceFill(['free_ended' => true])->save();
        $data = require database_path('migrations/2026_10_22_100001_end_free_ai_formation.php');
        $data->up();
        $data->up();

        $this->assertTrue($october->fresh()->isFreeEnded());
        $this->assertNull(FreeRegistration::query()->where('user_id', $student->id)->sole()->cancelled_at);
        $this->assertTrue($this->inGroup($student, $october));
        $this->assertSame(1, DB::table('formations')->where('slug', 'formation-gratuite-ia')->count());

        // Aucune autre formation gratuite touchée.
        $other = $this->freeFormation();
        $data->up();
        $this->assertFalse($other->fresh()->isFreeEnded());
    }

    /**
     * Réglages envoyés au composant d'Ama dans le navigateur (attribut x-data de la page).
     *
     * @return array<string, mixed>
     */
    private function browserConfig(TestResponse $response): array
    {
        $html = (string) $response->assertOk()->getContent();
        $this->assertSame(1, preg_match("/x-data=\"voiceAssistant\\(JSON\\.parse\\('(.*?)'\\)\\)\"/", $html, $match));

        return json_decode((string) json_decode('"'.$match[1].'"'), true);
    }
}
