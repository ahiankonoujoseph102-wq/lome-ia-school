<?php

namespace Tests\Feature\Packs;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Les migrations de la fin des inscriptions reprennent sans erreur quel que soit l'état de la base (MySQL
 * n'annule pas une modification de structure) : un simple ajout de colonne, et une date posée sans doublon.
 */
class PackDeadlineMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function structure(): object
    {
        return require database_path('migrations/2026_10_22_100000_add_registration_deadline_to_formations.php');
    }

    private function data(): object
    {
        return require database_path('migrations/2026_10_22_100001_set_pack_registration_deadline.php');
    }

    public function test_both_migrations_run_again_without_error_or_change(): void
    {
        $before = DB::table('formations')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->structure()->up();
        $this->data()->up();

        $this->assertTrue(Schema::hasColumn('formations', 'registration_ends_at'));
        $this->assertSame($before, DB::table('formations')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
    }

    public function test_it_resumes_when_the_column_is_missing_and_keeps_every_existing_row(): void
    {
        $rows = DB::table('formations')->count();
        Schema::table('formations', fn (Blueprint $table) => $table->dropColumn('registration_ends_at'));

        $this->structure()->up();
        $this->data()->up();

        $this->assertTrue(Schema::hasColumn('formations', 'registration_ends_at'));
        $this->assertSame($rows, DB::table('formations')->count());
        $this->assertSame(
            ['accompagnement-ia-niveau-1' => '2026-10-12 16:00:00', 'accompagnement-ia-niveau-2' => '2026-10-12 16:00:00', 'accompagnement-ia-niveau-3' => '2026-10-12 16:00:00'],
            DB::table('formations')->whereNotNull('registration_ends_at')->orderBy('slug')->pluck('registration_ends_at', 'slug')->all(),
        );
    }

    public function test_it_adds_no_index(): void
    {
        foreach (Schema::getIndexes('formations') as $index) {
            $this->assertNotContains('registration_ends_at', $index['columns']);
            $this->assertLessThanOrEqual(64, strlen($index['name']));
        }
    }
}
