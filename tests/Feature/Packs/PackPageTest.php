<?php

namespace Tests\Feature\Packs;

use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\User;
use App\Support\SearchEngines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Page « Accompagnement » (/accompagnement) : les trois offres, leurs formations incluses, le total des prix
 * séparés calculé en base, et rien de visible tant qu'aucune offre n'est publiée.
 */
class PackPageTest extends TestCase
{
    use RefreshDatabase;

    /** @var Collection<int, Formation> */
    private Collection $packs;

    private Formation $chatgpt;

    private Formation $posters;

    protected function setUp(): void
    {
        parent::setUp();

        // Offres créées (masquées) par la migration ; leurs formations sont ajoutées ici. Sans date de fin des
        // inscriptions (aucune limite), pour que ces tests ne dépendent pas du jour où ils tournent (voir PackDeadlineTest).
        Formation::query()->where('kind', 'pack')->update(['registration_ends_at' => null]);
        $this->packs = Formation::query()->where('kind', 'pack')->orderBy('sort_order')->get();
        $vip = Formation::query()->where('slug', 'groupe-vip')->sole();
        $this->chatgpt = Formation::factory()->create(['title' => 'Formation ChatGPT & Claude', 'price' => 5500]);
        $this->posters = Formation::factory()->create(['title' => 'Création d\'affiches pro avec l\'IA', 'price' => 36000]);
        $this->packs[0]->includedFormations()->sync([$this->chatgpt->id => ['position' => 1], $this->posters->id => ['position' => 2], $vip->id => ['position' => 3]]);
    }

    private function publish(): void
    {
        Formation::query()->where('kind', 'pack')->update(['is_active' => true]);
    }

    public function test_the_page_does_not_exist_while_no_pack_is_published(): void
    {
        $this->get(route('packs'))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('packs'))->assertNotFound();
        $this->get(route('formations.show', $this->packs[0]))->assertNotFound();
        $this->get(route('checkout.show', $this->packs[0]))->assertNotFound();

        $this->assertNotContains(route('packs'), collect(SearchEngines::sitemapUrls())->pluck('loc')->all());
    }

    public function test_the_admin_previews_the_hidden_packs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('packs'))
            ->assertOk()
            ->assertSee('Aperçu réservé à l\'administrateur', false)
            ->assertSee('Créateur de contenu')
            ->assertDontSee('data-test="pack-choose"', false)
            // Offres masquées : ni bouton du haut, ni barre fixe.
            ->assertDontSee('data-test="packs-hero-button"', false)
            ->assertDontSee('data-test="packs-sticky-bar"', false);
    }

    public function test_the_three_packs_are_shown_with_their_validated_texts(): void
    {
        $this->publish();

        $response = $this->get(route('packs'))->assertOk();

        // Nom court du niveau (fin du titre), du niveau 1 au niveau 3.
        $response->assertSeeInOrder([
            'data-test="pack-name">Créateur de contenu<',
            'data-test="pack-name">Créateur de sites et d&#039;applications<',
            'data-test="pack-name">Parcours complet avec suivi personnel<',
        ], false);
        $response->assertSeeInOrder(['Niveau 1', 'Niveau 2', 'Niveau 3']);
        $response->assertSee("10\u{00A0}000\u{00A0}FCFA")->assertSee("30\u{00A0}000\u{00A0}FCFA")->assertSee("75\u{00A0}000\u{00A0}FCFA");

        foreach ($this->packs as $pack) {
            $response->assertSee(route('checkout.show', $pack));
        }

        $this->assertSame(3, substr_count($response->getContent(), 'data-test="pack-choose"'));
        $response->assertSee('Je m\'inscris maintenant', false)->assertDontSee('Choisir cette offre');
        $response->assertSee('Formation ChatGPT &amp; Claude', false)->assertSee('Groupe VIP');

        // Points communs sous les cartes, une ligne chacun.
        $response->assertSee('Un document, un résumé et un exercice pour chaque formation')
            ->assertSee('L&#039;accès au groupe VIP', false)
            ->assertSee('Un accompagnement à vie')
            ->assertSee('Une attestation de participation à la fin');

        // Le niveau 3 (75 000 FCFA, au-dessus du seuil) s'ouvre après vérification ; les autres dès la capture.
        $response->assertSee('Accès ouvert après vérification du paiement')->assertSee('Accès dès l\'envoi de la capture du paiement', false);
    }

    public function test_the_separate_total_comes_from_the_real_prices(): void
    {
        $this->publish();

        $this->get(route('packs'))->assertOk()
            ->assertSee("Formations achetées séparément : <s class=\"tabular-nums\">41\u{00A0}500\u{00A0}FCFA</s>", false);

        // Un prix change dans l'admin : le total suit.
        $this->posters->update(['price' => 40000]);
        $this->get(route('packs'))->assertSee("45\u{00A0}500\u{00A0}FCFA");

        // Total inférieur ou égal au prix de l'offre : rien n'est affiché.
        $this->posters->update(['price' => 1000]);
        $this->get(route('packs'))->assertDontSee('data-test="pack-separate-price"', false);
    }

    public function test_no_forbidden_wording_is_added(): void
    {
        $this->publish();

        $html = mb_strtolower(strip_tags((string) $this->get(route('packs'))->getContent()));

        foreach (['certificat', 'diplôme', 'certifié', 'témoignage', 'avis', 'garanti', 'places limitées', 'dernière chance', 'il ne reste', 'offre expire', 'compte à rebours', 'gagner de l\'argent', 'revenus'] as $word) {
            $this->assertStringNotContainsString($word, $html, $word);
        }
    }

    public function test_a_buyer_sees_that_the_pack_is_already_active(): void
    {
        $this->publish();
        $student = User::factory()->create();
        $student->enroll($this->packs[0], EnrollmentSource::Admin);

        $this->actingAs($student)->get(route('packs'))
            ->assertOk()
            ->assertSee('Offre déjà active : voir mes formations')
            ->assertSee(route('checkout.show', $this->packs[1]));
    }

    public function test_a_published_pack_page_is_reachable_and_in_the_sitemap(): void
    {
        $this->publish();

        $this->get(route('formations.show', $this->packs[0]))->assertRedirect(route('packs').'#'.$this->packs[0]->slug);
        $this->get(route('checkout.show', $this->packs[0]))->assertOk()
            ->assertSee($this->packs[0]->title)
            ->assertSeeInOrder(['Formation ChatGPT &amp; Claude', 'Création d&#039;affiches pro avec l&#039;IA', 'Groupe VIP'], false)
            ->assertDontSee('Groupe privé d\'accompagnement', false);
        // Récapitulatif d'une formation habituelle inchangé.
        $this->get(route('checkout.show', $this->chatgpt))->assertOk()->assertSee('Groupe privé d\'accompagnement', false)->assertDontSee('data-test="checkout-pack-item"', false);
        $this->assertContains(route('packs'), collect(SearchEngines::sitemapUrls())->pluck('loc')->all());
        $this->assertContains('packs', SearchEngines::INDEXABLE_ROUTES);
    }

    public function test_the_page_fits_a_phone(): void
    {
        $this->publish();

        $html = (string) $this->get(route('packs'))->getContent();

        // Cartes empilées sur téléphone, trois colonnes seulement sur grand écran ; boutons pleine largeur.
        $this->assertStringContainsString('grid gap-6 lg:grid-cols-3', $html);
        $this->assertStringContainsString('btn-gold w-full', $html);
        // Style stable face au mode sombre forcé : jamais de voile « bg-white/… ».
        $this->assertDoesNotMatchRegularExpression('/\bbg-white\//', (string) file_get_contents(resource_path('views/site/packs.blade.php')));
    }
}
