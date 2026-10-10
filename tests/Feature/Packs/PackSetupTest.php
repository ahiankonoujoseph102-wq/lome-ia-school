<?php

namespace Tests\Feature\Packs;

use App\Enums\FormationKind;
use App\Models\ChatGroup;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Packs\PackSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Création des trois offres d'accompagnement et du Groupe VIP : masqués, sans doublon, formations retrouvées
 * par leur titre réel, aucune formation inventée.
 */
class PackSetupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Titres et prix réels des formations (FormationSeeder), dans un ordre mélangé.
     *
     * @var array<string, int>
     */
    private const array CATALOG = [
        'Création de SaaS avec l\'IA' => 20500,
        'Formation ChatGPT & Claude' => 5500,
        'Création d\'affiches pro avec l\'IA' => 36000,
        'Création de vidéos publicitaires avec l\'IA' => 5500,
        'Création de vidéos d\'histoire avec l\'IA' => 5500,
        'Création de vidéos films avec l\'IA' => 5500,
        'Création de site vitrine web avec l\'IA' => 5500,
        'Création de sites web avec base de données et projets web avancés' => 15500,
        'Création d\'applications et logiciels avec l\'IA' => 15500,
        'Création d\'agents IA' => 30000,
        'Automatisation avec l\'IA' => 25500,
        'Création de contenu TikTok, Facebook et YouTube avec les vidéos IA' => 15500,
        'Créer des formations et apprendre n\'importe quelle compétence avec l\'IA' => 12500,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // La migration a déjà tourné sur la base de test (sans formations) : on repart de la situation de la production.
        Formation::query()->whereIn('kind', [FormationKind::Pack->value, FormationKind::Group->value])->delete();

        foreach (self::CATALOG as $title => $price) {
            Formation::factory()->create(['title' => $title, 'price' => $price]);
        }
    }

    private function pack(int $level): Formation
    {
        return Formation::query()->where('slug', 'accompagnement-ia-niveau-'.$level)->sole();
    }

    /**
     * @return list<string>
     */
    private function includedTitles(Formation $pack): array
    {
        return $pack->includedFormations()->pluck('title')->all();
    }

    public function test_it_creates_the_three_hidden_packs_with_the_validated_texts(): void
    {
        $report = PackSetup::run();

        $levels = [1 => 10000, 2 => 30000, 3 => 75000];

        foreach ($levels as $level => $price) {
            $pack = $this->pack($level);
            $this->assertSame(FormationKind::Pack, $pack->kind);
            $this->assertFalse($pack->is_active, 'Offre créée masquée');
            $this->assertSame($price, $pack->price);
            $this->assertSame(PackSetup::PACKS[$level - 1]['description'], $pack->description);
            $this->assertSame(PackSetup::PACKS[$level - 1]['title'], $pack->title);
        }

        $this->assertSame('Accompagnement IA, niveau 1 : Créateur de contenu', $this->pack(1)->title);
        $this->assertStringStartsWith('Apprenez à créer des affiches et des vidéos professionnelles avec l\'IA.', $this->pack(1)->description);
        $this->assertStringContainsString('à partir du lundi 12 octobre 2026, en présentiel à Lomé et en ligne.', $this->pack(3)->description);

        $this->assertSame([
            'Formation ChatGPT & Claude',
            'Création d\'affiches pro avec l\'IA',
            'Création de vidéos publicitaires avec l\'IA',
            'Création de vidéos d\'histoire avec l\'IA',
            'Groupe VIP',
        ], $this->includedTitles($this->pack(1)));

        $this->assertSame([
            'Formation ChatGPT & Claude',
            'Création d\'affiches pro avec l\'IA',
            'Création de vidéos publicitaires avec l\'IA',
            'Création de vidéos d\'histoire avec l\'IA',
            'Création de site vitrine web avec l\'IA',
            'Création de sites web avec base de données et projets web avancés',
            'Création d\'applications et logiciels avec l\'IA',
            'Groupe VIP',
        ], $this->includedTitles($this->pack(2)));

        $this->assertSame([
            'Formation ChatGPT & Claude',
            'Création d\'affiches pro avec l\'IA',
            'Création de vidéos publicitaires avec l\'IA',
            'Création de vidéos d\'histoire avec l\'IA',
            'Création de site vitrine web avec l\'IA',
            'Création de sites web avec base de données et projets web avancés',
            'Création d\'applications et logiciels avec l\'IA',
            'Création d\'agents IA',
            'Automatisation avec l\'IA',
            'Création de SaaS avec l\'IA',
            'Groupe VIP',
        ], $this->includedTitles($this->pack(3)));

        foreach ($report['packs'] as $pack) {
            $this->assertSame([], $pack['missing']);
        }

        // Total des formations achetées séparément, calculé à partir des prix en base.
        $this->assertSame(5500 + 36000 + 5500 + 5500, $this->pack(1)->separatePrice());
        $this->assertSame(52500 + 5500 + 15500 + 15500, $this->pack(2)->separatePrice());
        $this->assertSame(89000 + 30000 + 25500 + 20500, $this->pack(3)->separatePrice());
    }

    public function test_the_vip_group_is_created_hidden_with_its_chat_group_and_no_pack_has_one(): void
    {
        PackSetup::run();

        $vip = Formation::query()->where('slug', PackSetup::VIP_SLUG)->sole();
        $this->assertSame(FormationKind::Group, $vip->kind);
        $this->assertSame('Groupe VIP', $vip->title);
        $this->assertFalse($vip->is_active);
        $this->assertSame(1, ChatGroup::query()->where('formation_id', $vip->id)->count());
        $this->assertSame(0, ChatGroup::query()->whereIn('formation_id', Formation::query()->where('kind', 'pack')->select('id'))->count());
    }

    public function test_it_runs_again_without_duplicate_and_never_overwrites_a_pack(): void
    {
        PackSetup::run();
        $this->pack(1)->update(['title' => 'Titre modifié dans l\'admin', 'price' => 12000]);
        $this->pack(1)->includedFormations()->detach(Formation::query()->where('title', 'Formation ChatGPT & Claude')->value('id'));

        $report = PackSetup::run();
        $this->artisan('app:create-packs')->assertSuccessful()->expectsOutputToContain('déjà présente, non modifiée');

        $this->assertSame(3, Formation::query()->where('kind', 'pack')->count());
        $this->assertSame(1, Formation::query()->where('kind', 'group')->count());
        $this->assertSame(1, ChatGroup::query()->where('formation_id', Formation::query()->where('kind', 'group')->value('id'))->count());
        $this->assertSame('Titre modifié dans l\'admin', $this->pack(1)->title);
        $this->assertSame(12000, $this->pack(1)->price);
        $this->assertNotContains('Formation ChatGPT & Claude', $this->includedTitles($this->pack(1)));
        $this->assertSame('déjà présente, non modifiée', $report['packs'][0]['status']);
        $this->assertSame(11, DB::table('formation_pack_items')->where('pack_id', $this->pack(3)->id)->count());
    }

    public function test_a_formation_that_does_not_exist_is_reported_and_never_invented(): void
    {
        Formation::query()->where('title', 'Création de SaaS avec l\'IA')->delete();
        $count = Formation::query()->count();

        $report = PackSetup::run();

        $this->assertSame(['SaaS'], $report['packs'][2]['missing']);
        $this->assertNotContains('Création de SaaS avec l\'IA', $this->includedTitles($this->pack(3)));
        // Toujours citée dans la description, rien d'autre de créé (3 offres et le Groupe VIP).
        $this->assertStringContainsString('création de SaaS', $this->pack(3)->description);
        $this->assertSame($count + 4, Formation::query()->count());

        $this->artisan('app:create-packs')->assertSuccessful()->expectsOutputToContain('Introuvables dans la base (seulement citées dans la description) : SaaS');
    }

    public function test_titles_are_found_whatever_the_case_or_accents_but_never_when_ambiguous(): void
    {
        Formation::query()->where('title', 'Automatisation avec l\'IA')->update(['title' => 'AUTOMATISATION avec l’IA (niveau avancé)']);
        Formation::factory()->create(['title' => 'Affiches de Noël', 'price' => 5000]);

        $matches = PackSetup::findFormations();

        $this->assertSame('AUTOMATISATION avec l’IA (niveau avancé)', $matches['automation']['title'] ?? null);
        // Deux formations parlent d'affiches : aucune n'est rattachée au hasard.
        $this->assertNull($matches['posters']);
    }

    public function test_no_purchase_is_touched(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->create(['user_id' => $student->id, 'formation_id' => Formation::query()->where('kind', 'course')->value('id')]);
        $before = $purchase->fresh()?->toArray();

        PackSetup::run();

        $this->assertSame(1, Purchase::query()->count());
        $this->assertEquals($before, $purchase->fresh()?->toArray());
    }
}
