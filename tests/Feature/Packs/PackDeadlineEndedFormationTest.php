<?php

namespace Tests\Feature\Packs;

use App\Models\Formation;
use App\Models\User;
use App\Packs\PacksPage;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fin des inscriptions des offres d'accompagnement et formation gratuite terminée, ensemble.
 *
 * Une offre close ne compte plus comme « en vente » : quand toutes les offres sont closes, le bouton « Découvrir
 * l'accompagnement » de la formation terminée (haut de page et barre du bas) disparaît et Ama n'oriente plus vers
 * l'accompagnement. Le lien « Accompagnement » du menu reste tant qu'une offre est publiée, même close : la page
 * affiche alors « Inscriptions closes », en haut de chaque carte à la place du compte à rebours.
 */
class PackDeadlineEndedFormationTest extends TestCase
{
    use RefreshDatabase;

    private Formation $free;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.gemini.key' => 'cle-de-test', 'services.gemini.model' => 'modele-de-test']);
        SiteSettings::save(['site.voice_assistant' => ['enabled' => true, 'provider' => 'gemini']]);

        // Données réelles des migrations : trois offres (fin des inscriptions le lundi 12 octobre 2026 à 16 h),
        // publiées ici, et la formation gratuite du 10 octobre, marquée terminée, publiée ici.
        Formation::query()->where('kind', 'pack')->update(['is_active' => true]);
        $this->free = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        $this->free->forceFill(['is_active' => true])->save();
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse($time, 'Africa/Lome'));
    }

    /**
     * Réglages envoyés au composant d'Ama dans le navigateur (attribut x-data de la page).
     *
     * @return array<string, mixed>
     */
    private function amaConfig(TestResponse $response): array
    {
        $html = (string) $response->assertOk()->getContent();
        $this->assertSame(1, preg_match("/x-data=\"voiceAssistant\\(JSON\\.parse\\('(.*?)'\\)\\)\"/", $html, $match));

        return json_decode((string) json_decode('"'.$match[1].'"'), true);
    }

    public function test_before_the_deadline_the_ended_formation_leads_to_the_accompaniment(): void
    {
        $this->at('2026-10-12 15:59');

        $this->assertTrue($this->free->isFreeEnded());
        $this->assertTrue(PacksPage::onSale());

        $page = $this->get(route('formations.show', $this->free))->assertOk()
            ->assertSee('data-test="free-ended-button"', false)
            ->assertSee('data-test="free-bar-ended"', false)
            ->assertSee('Découvrir l\'accompagnement')
            ->assertSee('data-test="menu-packs"', false);

        $ama = $this->amaConfig($page);
        $this->assertSame('ended', $ama['nudge']['type']);
        $this->assertStringContainsString('accompagnement', $ama['context']['role_page']);

        $this->get(route('packs'))->assertOk()
            ->assertSee('data-test="pack-deadline"', false)
            ->assertSee('data-test="pack-choose"', false)
            ->assertDontSee('data-test="pack-closed-notice"', false);
    }

    public function test_once_every_offer_is_closed_the_button_and_ama_no_longer_lead_to_the_accompaniment(): void
    {
        $this->at('2026-10-12 16:00');

        $this->assertTrue(Formation::query()->publishedPacks()->get()->every->registrationsClosed());
        $this->assertFalse(PacksPage::onSale());
        $this->assertTrue(PacksPage::published());
        $this->assertNull($this->free->fresh()->endedButton());

        $page = $this->get(route('formations.show', $this->free))->assertOk()
            ->assertSee('Formation terminée')
            ->assertSee('data-test="free-bar-price"', false)
            ->assertDontSee('data-test="free-ended-button"', false)
            ->assertDontSee('data-test="free-ended-block-button"', false)
            ->assertDontSee('data-test="free-bar-ended"', false)
            ->assertDontSee('Découvrir l\'accompagnement')
            ->assertDontSee('Je m\'inscris');

        // Ama : bulle habituelle d'une formation, plus aucune orientation vers l'accompagnement.
        $ama = $this->amaConfig($page);
        $this->assertSame('formation', $ama['nudge']['type']);
        $this->assertStringNotContainsString('accompagnement', $ama['nudge']['text']);
        $this->assertStringNotContainsString('accompagnement', $ama['context']['role_page']);
        $this->assertStringContainsString('ne propose jamais de s\'y inscrire', $ama['context']['role_page']);
        $this->assertStringContainsString('catalogue des formations', $ama['context']['role_page']);

        // L'admin est prévenu que le bouton est masqué.
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.free-registrations.show', $this->free))
            ->assertOk()->assertSee('data-test="free-ended-hidden-button"', false);
    }

    public function test_the_menu_link_stays_while_an_offer_is_published_even_closed_and_the_page_says_so(): void
    {
        $this->at('2026-10-13 09:00');

        $this->get(route('home'))->assertOk()->assertSee('data-test="menu-packs"', false);

        $page = $this->get(route('packs'))->assertOk();
        $html = (string) $page->getContent();

        // Chaque carte : la mention en haut (à la place du compte à rebours) et à la place du bouton d'achat.
        $this->assertSame(3, substr_count($html, 'data-test="pack-closed-notice"'));
        $this->assertSame(3, substr_count($html, 'data-test="pack-closed"'));
        $page->assertDontSee('data-test="pack-deadline"', false)
            ->assertDontSee('data-test="pack-countdown"', false)
            ->assertDontSee('data-test="pack-choose"', false)
            ->assertSee('Inscriptions closes');

        // La mention est en haut de la carte, avant la description et la liste des formations incluses.
        $this->assertLessThan(strpos($html, 'data-test="pack-description"'), strpos($html, 'data-test="pack-closed-notice"'));

        // Plus aucune offre publiée : ni lien, ni page (l'adresse mène au catalogue).
        Formation::query()->where('kind', 'pack')->update(['is_active' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="menu-packs"', false);
        $this->get(route('packs'))->assertRedirect(route('formations.index'));
    }

    public function test_a_single_offer_still_open_keeps_the_button(): void
    {
        $this->at('2026-10-13 09:00');

        // Une offre dont la date est repoussée : de nouveau en vente.
        Formation::query()->where('slug', 'accompagnement-ia-niveau-2')->update(['registration_ends_at' => '2026-10-20 16:00:00']);

        $this->assertTrue(PacksPage::onSale());
        $this->get(route('formations.show', $this->free))->assertOk()
            ->assertSee('data-test="free-ended-button"', false)
            ->assertSee('data-test="free-bar-ended"', false);
        $this->assertSame('ended', $this->amaConfig($this->get(route('formations.show', $this->free)))['nudge']['type']);

        $html = (string) $this->get(route('packs'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-test="pack-closed-notice"'));
        $this->assertSame(1, substr_count($html, 'data-test="pack-deadline"'));
        $this->assertSame(1, substr_count($html, 'data-test="pack-choose"'));

        // Une offre sans date de fin compte aussi comme en vente.
        Formation::query()->where('slug', 'accompagnement-ia-niveau-2')->update(['registration_ends_at' => null]);
        $this->assertTrue(PacksPage::onSale());
    }

    public function test_another_internal_destination_stays_after_the_deadline(): void
    {
        $this->at('2026-10-13 09:00');

        $this->free->forceFill(['free_ended_button_url' => '/formations', 'free_ended_button_label' => 'Voir les formations'])->save();

        $page = $this->get(route('formations.show', $this->free))->assertOk()
            ->assertSee('data-test="free-ended-button"', false)
            ->assertSee('data-test="free-bar-ended"', false)
            ->assertSee('Voir les formations');

        // Ama ne parle pas d'accompagnement : le bouton mène au catalogue.
        $ama = $this->amaConfig($page);
        $this->assertSame('formation', $ama['nudge']['type']);
        $this->assertStringNotContainsString('offres d\'accompagnement', $ama['context']['role_page']);
    }
}
