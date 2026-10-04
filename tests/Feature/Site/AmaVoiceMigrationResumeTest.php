<?php

namespace Tests\Feature\Site;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migration des compteurs de la voix naturelle d'Ama reprend sans erreur quel que soit l'état de la base (MySQL
 * n'annule pas une modification de structure qui échoue au milieu), avec des noms d'index de 64 caractères au plus.
 */
class AmaVoiceMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_16_100000_create_assistant_voice_usage_table.php');
    }

    private function assertSchemaComplete(): void
    {
        $this->assertTrue(Schema::hasColumns('assistant_voice_usage', ['id', 'day', 'spoken', 'cached', 'google_requests', 'refusals', 'fallbacks', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasIndex('assistant_voice_usage', ['day'], 'unique'));
    }

    public function test_every_index_name_fits_the_mysql_limit_of_64_characters(): void
    {
        foreach (Schema::getIndexes('assistant_voice_usage') as $index) {
            $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
        }

        $migration = $this->migration();
        $this->assertLessThanOrEqual(64, strlen($migration::DAY_UNIQUE));
    }

    public function test_it_runs_again_on_a_complete_database(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_resumes_after_a_failure_halfway(): void
    {
        // Échec après la création de la table, avant l'index unique.
        Schema::drop('assistant_voice_usage');
        Schema::create('assistant_voice_usage', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->unsignedInteger('spoken')->default(0);
            $table->unsignedInteger('cached')->default(0);
            $table->unsignedInteger('google_requests')->default(0);
            $table->unsignedInteger('refusals')->default(0);
            $table->unsignedInteger('fallbacks')->default(0);
            $table->timestamps();
        });

        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_starts_from_an_empty_database(): void
    {
        $this->migration()->down();
        $this->migration()->up();

        $this->assertSchemaComplete();
    }
}
