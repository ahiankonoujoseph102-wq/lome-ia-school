<?php

namespace Tests\Feature\Packs;

use App\Enums\EnrollmentSource;
use App\Models\Formation;
use App\Models\User;
use App\Packs\PacksPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Page de vente /accompagnement : en-tête (promesse, compte à rebours, bouton vers les offres), cartes compactes
 * (nom court, prix, accroche, quatre points forts, « Je m'inscris maintenant », détail replié), « Le plus complet »
 * sur le dernier niveau, « Comment s'inscrire », « Dans toutes les offres », dernier bouton et barre fixe sur
 * téléphone ; tout disparaît au profit de « Inscriptions closes » une fois les inscriptions closes.
 * Dates de fin toujours fixées par rapport à l'heure du test : rien ne dépend du jour où il tourne.
 */
class PackSalesPageTest extends TestCase
{
    use RefreshDatabase;

    /** @var Collection<int, Formation> */
    private Collection $packs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfMinute());
        Formation::query()->where('kind', 'pack')->update(['is_active' => true, 'registration_ends_at' => now()->addDays(2)->addHours(3)]);
        $this->packs = Formation::query()->where('kind', 'pack')->orderBy('sort_order')->get();
        $chatgpt = Formation::factory()->create(['title' => 'Formation ChatGPT & Claude', 'price' => 5500]);
        $vip = Formation::query()->where('slug', 'groupe-vip')->sole();
        $this->packs[0]->includedFormations()->sync([$chatgpt->id => ['position' => 1], $vip->id => ['position' => 2]]);
    }

    private function html(): string
    {
        return (string) $this->get(route('packs'))->assertOk()->getContent();
    }

    public function test_the_page_follows_the_sales_structure_from_top_to_bottom(): void
    {
        $this->get(route('packs'))->assertOk()->assertSeeInOrder([
            'data-test="packs-hero"',
            'data-test="packs-promise"',
            'data-test="pack-deadline"',
            'data-test="packs-hero-button"',
            'id="offres"',
            'data-test="pack-card"',
            'data-test="packs-steps"',
            'data-test="packs-common"',
            'data-test="packs-final"',
            'data-test="packs-final-button"',
            'data-test="packs-sticky-bar"',
        ], false);
    }

    public function test_the_header_has_a_countdown_and_a_button_to_the_offers(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('Inscriptions ouvertes jusqu\'au', $html);
        $this->assertMatchesRegularExpression('#data-test="pack-countdown-days">2<#', $html);
        $this->assertMatchesRegularExpression('#<a href="\#offres" class="btn-gold w-full[^"]*" [^>]*data-test="packs-hero-button"[^>]*>Je m\'inscris maintenant</a>#', $html);
        // Une seule date pour les trois offres : pas de deuxième compte à rebours sur les cartes.
        $this->assertSame(1, substr_count($html, 'data-test="pack-deadline"'));
    }

    public function test_each_card_is_compact_with_the_button_as_its_main_element(): void
    {
        Formation::query()->where('kind', 'pack')->update(['description' => "Accroche.\n- Un\n- Deux\n- Trois\n- Quatre\n- Cinq"]);

        $html = $this->html();

        $this->assertSame(3, substr_count($html, 'data-test="pack-card"'));
        $this->assertSame(3, substr_count($html, 'data-test="pack-points"'));
        $this->assertSame(3, substr_count($html, 'Voir tout ce qui est inclus'));

        foreach ($this->packs as $pack) {
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('checkout.show', $pack), '#').'" class="btn-gold w-full !py-4 text-lg"[^>]*data-test="pack-choose"[^>]*>Je m\'inscris maintenant</a>#', $html);
        }

        // Quatre points forts au plus par carte.
        preg_match_all('#data-test="pack-points">(.*?)</ul>#s', $html, $lists);
        foreach ($lists[1] as $list) {
            $this->assertLessThanOrEqual(4, substr_count($list, '<li'));
        }

        // La liste complète des formations incluses est repliée (élément <details>, sans JavaScript).
        $this->assertMatchesRegularExpression('#<details class="group mt-4" data-test="pack-more">.*?data-test="pack-included".*?Formation ChatGPT &amp; Claude.*?Groupe VIP.*?</details>#s', $html);
    }

    public function test_only_the_last_level_is_shown_as_the_most_complete(): void
    {
        $html = $this->html();

        $this->assertSame(1, substr_count($html, 'data-test="pack-featured"'));
        $position = strpos($html, 'Le plus complet');
        $this->assertGreaterThan(strpos($html, 'id="accompagnement-ia-niveau-3"'), $position);
        $this->assertLessThan(strpos($html, 'data-test="pack-name">Parcours complet avec suivi personnel<'), $position);
    }

    public function test_the_sticky_bar_shows_the_time_left_and_leaves_room_for_the_content(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression('#<div\s+data-bottom-bar\s+data-install-avoid\s+class="fixed inset-x-0 bottom-0 z-40[^"]*lg:hidden"#', $html);
        $this->assertMatchesRegularExpression('#data-test="packs-sticky-countdown">.*?<span x-text="days">2</span>&nbsp;j <span x-text="pad\(hours\)">03</span>&nbsp;h <span x-text="pad\(minutes\)">00</span>&nbsp;min <span x-text="pad\(seconds\)"[^>]*>00</span>&nbsp;s#s', $html);
        $this->assertMatchesRegularExpression('#<a href="\#offres" class="btn-gold[^"]*" data-test="packs-sticky-button">Je m\'inscris maintenant</a>#', $html);
        // Place réservée en bas de page (et le bouton d'Ama remonté au-dessus de la barre s'il est affiché).
        $this->assertMatchesRegularExpression('#<body class="[^"]*pb-24 lg:pb-0[^"]*has-sticky-cta#', $html);
    }

    public function test_the_registration_steps_describe_the_real_mobile_money_journey(): void
    {
        $this->get(route('packs'))->assertSeeInOrder([
            'Comment s&#039;inscrire',
            'créez votre compte (ou connectez-vous) et confirmez votre e-mail',
            'Choisissez votre pays et votre opérateur, puis envoyez le montant par Mobile Money au numéro affiché.',
            'Envoyez la capture de votre transfert',
        ], false);
    }

    public function test_after_the_deadline_every_button_gives_way_to_registrations_closed(): void
    {
        $this->travel(2)->days();
        $this->travel(3)->hours();

        $html = $this->html();

        foreach (['packs-hero-button', 'pack-choose', 'packs-final-button', 'packs-sticky-bar', 'packs-steps', 'pack-countdown'] as $test) {
            $this->assertStringNotContainsString('data-test="'.$test.'"', $html, $test);
        }
        $this->assertStringNotContainsString('Je m\'inscris maintenant', $html);
        $this->assertStringNotContainsString('has-sticky-cta', $html);
        $this->assertStringContainsString('data-test="packs-closed"', $html);
        $this->assertStringContainsString('data-test="packs-final-closed"', $html);
        $this->assertSame(3, substr_count($html, 'data-test="pack-closed"'));
        foreach ($this->packs as $pack) {
            $this->assertStringNotContainsString(route('checkout.show', $pack), $html);
        }
    }

    public function test_one_minute_before_the_deadline_the_buttons_are_still_there(): void
    {
        $this->travel(2)->days();
        $this->travel(3)->hours();
        $this->travel(-1)->minutes();

        $html = $this->html();

        $this->assertStringContainsString('data-test="packs-hero-button"', $html);
        $this->assertStringContainsString('data-test="packs-sticky-bar"', $html);
        $this->assertSame(3, substr_count($html, 'data-test="pack-choose"'));
    }

    public function test_when_offers_close_at_different_times_each_card_keeps_its_own_date(): void
    {
        $this->packs[2]->update(['registration_ends_at' => now()->addDays(5)]);

        $html = $this->html();

        // Haut de page : la date la plus proche ; la carte du niveau 3 affiche la sienne.
        $this->assertSame(2, substr_count($html, 'data-test="pack-deadline"'));
        $this->assertMatchesRegularExpression('#data-test="pack-countdown-days">5<#', $html);

        // Les deux premières closes : le niveau 3 reste ouvert, les boutons communs aussi.
        $this->travel(3)->days();
        $html = $this->html();
        $this->assertSame(1, substr_count($html, 'data-test="pack-choose"'));
        $this->assertSame(2, substr_count($html, 'data-test="pack-closed"'));
        $this->assertStringContainsString('data-test="packs-hero-button"', $html);
        $this->assertStringContainsString('data-test="packs-sticky-bar"', $html);
    }

    public function test_without_any_deadline_the_buttons_have_no_countdown(): void
    {
        Formation::query()->where('kind', 'pack')->update(['registration_ends_at' => null]);

        $html = $this->html();

        $this->assertStringNotContainsString('data-test="pack-deadline"', $html);
        $this->assertStringNotContainsString('data-test="packs-sticky-countdown"', $html);
        $this->assertStringContainsString('data-test="packs-sticky-button"', $html);
        $this->assertStringContainsString('data-test="packs-hero-button"', $html);
    }

    public function test_a_buyer_of_one_offer_still_sees_the_others(): void
    {
        $student = User::factory()->create();
        $student->enroll($this->packs[0], EnrollmentSource::Admin);

        $html = (string) $this->actingAs($student)->get(route('packs'))->assertOk()->getContent();

        $this->assertStringContainsString('data-test="pack-owned"', $html);
        $this->assertSame(2, substr_count($html, 'data-test="pack-choose"'));
    }

    public function test_the_short_name_is_the_end_of_the_title(): void
    {
        $this->assertSame('Créateur de contenu', PacksPage::shortName('Accompagnement IA, niveau 1 : Créateur de contenu'));
        $this->assertSame('Offre spéciale', PacksPage::shortName('Offre spéciale'));
        $this->assertSame('Titre :', PacksPage::shortName('Titre :'));
    }
}
