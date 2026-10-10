<?php

namespace Tests\Feature\Packs;

use App\Enums\FormationKind;
use App\Models\Formation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migration des offres d'accompagnement reprend sans erreur quel que soit l'état de la base
 * (MySQL n'annule pas une modification de structure) et ne fait qu'ajouter des colonnes et une table.
 */
class PackMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_21_100000_add_packs_to_formations.php');
    }

    private function assertSchemaComplete(): void
    {
        $this->assertTrue(Schema::hasColumn('formations', 'kind'));
        $this->assertTrue(Schema::hasColumns('formation_pack_items', ['id', 'pack_id', 'formation_id', 'position', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumn('enrollments', 'pack_id'));
        $this->assertTrue(Schema::hasIndex('formations', ['kind']));
        $this->assertTrue(Schema::hasIndex('formation_pack_items', ['pack_id', 'formation_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('enrollments', ['pack_id']));

        $foreign = fn (string $table): array => collect(Schema::getForeignKeys($table))->map(fn (array $key): string => $key['columns'][0].'>'.$key['foreign_table'])->sort()->values()->all();
        $this->assertSame(['formation_id>formations', 'pack_id>formations'], $foreign('formation_pack_items'));
        $this->assertContains('pack_id>formations', $foreign('enrollments'));
    }

    public function test_every_index_and_key_name_fits_the_mysql_limit_of_64_characters(): void
    {
        foreach (['formations', 'formation_pack_items', 'enrollments'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
            }

            foreach (Schema::getForeignKeys($table) as $key) {
                $this->assertLessThanOrEqual(64, strlen((string) $key['name']), (string) $key['name']);
            }
        }

        // Noms générés par MySQL pour les clés étrangères (SQLite ne les nomme pas).
        foreach (['formation_pack_items_pack_id_foreign', 'formation_pack_items_formation_id_foreign', 'enrollments_pack_id_foreign'] as $name) {
            $this->assertLessThanOrEqual(64, strlen($name));
        }
    }

    public function test_it_runs_again_without_error_when_everything_already_exists(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_resumes_when_only_part_of_the_changes_were_made(): void
    {
        // Tentative précédente arrêtée après la colonne « kind » et la table, sans index ni colonne sur enrollments.
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropForeign(['pack_id']);
            $table->dropIndex('enrollments_pack_id_index');
            $table->dropColumn('pack_id');
        });
        Schema::table('formation_pack_items', fn (Blueprint $table) => $table->dropUnique('pack_items_pack_formation_unique'));
        Schema::table('formations', fn (Blueprint $table) => $table->dropIndex('formations_kind_index'));

        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_existing_formations_are_ordinary_courses(): void
    {
        // Une formation enregistrée sans type (comme toutes celles d'avant cette migration) est une formation habituelle.
        $id = DB::table('formations')->insertGetId([
            'title' => 'Ancienne formation',
            'slug' => 'ancienne-formation',
            'description' => 'Description',
            'price' => 5500,
            'category' => 'ai-basics',
            'formats' => '["recorded"]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(FormationKind::Course, Formation::query()->findOrFail($id)->kind);
    }
}
