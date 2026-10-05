<?php

namespace Tests\Feature\Site;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migration du deuxième fournisseur de voix (Cloudflare) reprend sans erreur quel que soit l'état de la base (MySQL
 * n'annule pas une modification de structure qui échoue au milieu), avec des noms d'index de 64 caractères au plus.
 */
class AmaCloudflareVoiceMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_18_100000_add_cloudflare_voice_tables.php');
    }

    private function assertSchemaComplete(): void
    {
        $this->assertTrue(Schema::hasColumns('assistant_voice_quota', ['id', 'provider', 'day', 'requests', 'audio_ms', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasIndex('assistant_voice_quota', ['provider', 'day'], 'unique'));
        $this->assertTrue(Schema::hasColumn('assistant_voice_events', 'provider'));
    }

    public function test_every_index_name_fits_the_mysql_limit_of_64_characters(): void
    {
        foreach (['assistant_voice_quota', 'assistant_voice_events'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
            }
        }

        $migration = $this->migration();
        $this->assertLessThanOrEqual(64, strlen($migration::QUOTA_UNIQUE));
    }

    public function test_it_runs_again_on_a_complete_database(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_resumes_after_a_failure_halfway(): void
    {
        // Échec après la création de la table, avant l'index unique et la nouvelle colonne.
        $this->migration()->down();
        Schema::create('assistant_voice_quota', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 20);
            $table->date('day');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('audio_ms')->default(0);
            $table->timestamps();
        });

        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_resumes_when_only_the_column_is_missing(): void
    {
        Schema::table('assistant_voice_events', fn (Blueprint $table) => $table->dropColumn('provider'));

        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_starts_from_an_empty_state(): void
    {
        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('assistant_voice_quota'));
        $this->assertFalse(Schema::hasColumn('assistant_voice_events', 'provider'));

        $this->migration()->up();

        $this->assertSchemaComplete();
    }
}
