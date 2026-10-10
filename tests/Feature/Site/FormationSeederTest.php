<?php

namespace Tests\Feature\Site;

use App\Enums\FormationCategory;
use App\Enums\FormationFormat;
use App\Models\Formation;
use Database\Seeders\FormationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_seventeen_formations_with_their_prices(): void
    {
        // La formation gratuite du 10 octobre 2026, les offres d'accompagnement et le Groupe VIP sont créés
        // par des migrations, pas par le seeder : hors du compte.
        Formation::query()->where('slug', 'formation-gratuite-ia')->delete();
        Formation::query()->whereIn('kind', ['pack', 'group'])->delete();
        $this->seed(FormationSeeder::class);

        $this->assertSame(17, Formation::count());
        $this->assertSame(5500, Formation::where('slug', 'chatgpt-claude-initiation-ia')->value('price'));
        $this->assertSame(36000, Formation::where('slug', 'creation-affiches-pro-ia')->value('price'));
        $this->assertSame(300000, Formation::where('slug', 'ebooks-livres-professionnels-ia')->value('price'));
        $this->assertSame(5100, Formation::where('slug', 'chansons-personnalisees-ia')->value('price'));

        $this->assertSame(
            collect(FormationCategory::cases())->map->value->sort()->values()->all(),
            Formation::query()->distinct()->pluck('category')->map->value->sort()->values()->all(),
        );

        Formation::all()->each(function (Formation $formation): void {
            $this->assertTrue($formation->hasFormat(FormationFormat::Recorded));
            $this->assertNotEmpty($formation->outcomes);
            // Aucun module ni aucune leçon inventés : le contenu est ajouté par l'admin.
            $this->assertSame(0, $formation->modules()->count());
        });
    }

    public function test_running_the_seeder_twice_does_not_create_duplicates(): void
    {
        // La formation gratuite du 10 octobre 2026, les offres d'accompagnement et le Groupe VIP sont créés
        // par des migrations, pas par le seeder : hors du compte.
        Formation::query()->where('slug', 'formation-gratuite-ia')->delete();
        Formation::query()->whereIn('kind', ['pack', 'group'])->delete();
        $this->seed(FormationSeeder::class);
        $this->seed(FormationSeeder::class);

        $this->assertSame(17, Formation::count());
    }
}
