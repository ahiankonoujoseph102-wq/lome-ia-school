<?php

namespace Tests\Feature\FreeRegistrations;

use App\Models\Formation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Les migrations de l'inscription gratuite reprennent sans erreur quel que soit l'état de la base.
 *
 * Cause de l'échec du déploiement (octobre 2026) : le nom automatique d'un index faisait 65 caractères, alors
 * que MySQL en accepte 64 ; MySQL n'annulant pas les modifications de structure, les colonnes, les tables,
 * l'index unique et les clés étrangères étaient déjà créés, et la tentative suivante échouait sur
 * « Duplicate column name 'event_date' ».
 */
class FreeRegistrationMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function schemaMigration(): object
    {
        return require database_path('migrations/2026_10_13_100000_create_free_registration_tables.php');
    }

    private function dataMigration(): object
    {
        return require database_path('migrations/2026_10_13_100001_add_free_ai_formation.php');
    }

    /**
     * @return list<string>
     */
    private function indexNames(string $table): array
    {
        return collect(Schema::getIndexes($table))->pluck('name')->sort()->values()->all();
    }

    private function assertSchemaComplete(): void
    {
        $this->assertTrue(Schema::hasColumns('formations', ['event_date', 'event_location']));
        $this->assertTrue(Schema::hasColumns('free_registration_options', ['id', 'formation_id', 'label', 'schedule', 'mode', 'capacity', 'position', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('free_registrations', ['id', 'formation_id', 'user_id', 'free_registration_option_id', 'cancelled_at', 'created_at', 'updated_at']));

        $this->assertTrue(Schema::hasIndex('free_registration_options', ['formation_id', 'position']));
        $this->assertTrue(Schema::hasIndex('free_registrations', ['formation_id', 'user_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('free_registrations', ['free_registration_option_id', 'cancelled_at']));

        $foreign = fn (string $table): array => collect(Schema::getForeignKeys($table))->map(fn (array $key): string => $key['columns'][0].'>'.$key['foreign_table'])->sort()->values()->all();
        $this->assertSame(['formation_id>formations'], $foreign('free_registration_options'));
        $this->assertSame(['formation_id>formations', 'free_registration_option_id>free_registration_options', 'user_id>users'], $foreign('free_registrations'));
    }

    public function test_every_index_name_fits_the_mysql_limit_of_64_characters(): void
    {
        foreach (['free_registration_options', 'free_registrations'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
            }
        }
    }

    public function test_it_resumes_when_event_date_already_exists(): void
    {
        // Base où seule la colonne event_date a été ajoutée par une tentative précédente.
        Schema::dropIfExists('free_registrations');
        Schema::dropIfExists('free_registration_options');
        Schema::table('formations', fn (Blueprint $table) => $table->dropColumn('event_location'));
        $this->assertTrue(Schema::hasColumn('formations', 'event_date'));

        $this->schemaMigration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_resumes_from_the_partial_state_left_by_the_failed_deployment(): void
    {
        // État de la production : tout est créé sauf l'index (option, annulation) au nom trop long.
        Schema::table('free_registrations', fn (Blueprint $table) => $table->dropIndex('free_reg_option_cancelled_index'));
        $this->assertFalse(Schema::hasIndex('free_registrations', ['free_registration_option_id', 'cancelled_at']));

        $this->schemaMigration()->up();
        $this->dataMigration()->up();

        $this->assertSchemaComplete();
        $this->assertSame(1, Formation::query()->where('slug', 'formation-gratuite-ia')->count());
    }

    public function test_it_runs_again_without_error_or_duplicate_when_everything_already_exists(): void
    {
        $formation = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        $indexes = ['free_registration_options' => $this->indexNames('free_registration_options'), 'free_registrations' => $this->indexNames('free_registrations')];
        $groups = DB::table('chat_groups')->where('formation_id', $formation->id)->count();

        $this->schemaMigration()->up();
        $this->dataMigration()->up();
        $this->schemaMigration()->up();
        $this->dataMigration()->up();

        $this->assertSchemaComplete();
        $this->assertSame($indexes['free_registration_options'], $this->indexNames('free_registration_options'));
        $this->assertSame($indexes['free_registrations'], $this->indexNames('free_registrations'));
        $this->assertSame(1, Formation::query()->where('slug', 'formation-gratuite-ia')->count());
        $this->assertSame(4, DB::table('free_registration_options')->where('formation_id', $formation->id)->count());
        $this->assertSame(1, $groups);
        $this->assertSame(1, DB::table('chat_groups')->where('formation_id', $formation->id)->count());
    }

    public function test_existing_data_is_kept_when_the_migrations_run_again(): void
    {
        $formation = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        $formation->forceFill(['title' => 'Titre modifié dans l\'admin', 'event_location' => 'Lomé'])->save();
        DB::table('free_registration_options')->where('formation_id', $formation->id)->where('label', 'Présentiel soir')->update(['capacity' => 25]);

        $this->schemaMigration()->up();
        $this->dataMigration()->up();

        $formation->refresh();
        $this->assertSame('Titre modifié dans l\'admin', $formation->title);
        $this->assertSame('Lomé', $formation->event_location);
        $this->assertSame(25, (int) DB::table('free_registration_options')->where('label', 'Présentiel soir')->value('capacity'));
    }

    public function test_a_missing_chat_group_is_added_but_never_duplicated(): void
    {
        $formation = Formation::query()->where('slug', 'formation-gratuite-ia')->sole();
        DB::table('chat_groups')->where('formation_id', $formation->id)->delete();

        $this->dataMigration()->up();
        $this->dataMigration()->up();

        $this->assertSame(1, DB::table('chat_groups')->where('formation_id', $formation->id)->count());
        $this->assertSame(4, DB::table('free_registration_options')->where('formation_id', $formation->id)->count());
    }
}
