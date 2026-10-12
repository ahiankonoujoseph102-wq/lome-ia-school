<?php

namespace Tests\Feature\Newsletters;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migration des envois groupés reprend sans erreur quel que soit l'état de la base (MySQL n'annule pas une
 * modification de structure qui échoue au milieu) et ne dépasse jamais 64 caractères pour un nom d'index.
 */
class NewsletterMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_25_100000_create_newsletter_tables.php');
    }

    private function assertSchemaComplete(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'bulk_email_unsubscribed_at'));
        $this->assertTrue(Schema::hasColumns('newsletters', ['user_id', 'subject', 'body', 'image_path', 'video_url', 'button_label', 'button_url', 'audience', 'status', 'recipients_count', 'started_at', 'completed_at', 'cancelled_at']));
        $this->assertTrue(Schema::hasColumns('newsletter_recipients', ['newsletter_id', 'user_id', 'status', 'attempts', 'retry_at', 'queued_at', 'sent_at', 'unsubscribed_at', 'last_error']));
        $this->assertTrue(Schema::hasIndex('newsletter_recipients', ['newsletter_id', 'user_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('newsletter_recipients', ['status', 'newsletter_id']));
        $this->assertTrue(Schema::hasIndex('newsletter_recipients', ['sent_at']));

        $foreign = collect(Schema::getForeignKeys('newsletter_recipients'))->map(fn (array $key): string => $key['columns'][0].'>'.$key['foreign_table'])->sort()->values()->all();
        $this->assertSame(['newsletter_id>newsletters', 'user_id>users'], $foreign);
        $this->assertSame(['user_id>users'], collect(Schema::getForeignKeys('newsletters'))->map(fn (array $key): string => $key['columns'][0].'>'.$key['foreign_table'])->all());
    }

    public function test_every_index_name_fits_the_mysql_limit_of_64_characters(): void
    {
        foreach (['newsletters', 'newsletter_recipients'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
            }

            foreach (Schema::getForeignKeys($table) as $key) {
                $this->assertLessThanOrEqual(64, strlen((string) $key['name']), (string) $key['name']);
            }
        }

        $migration = $this->migration();

        foreach ([$migration::RECIPIENT_UNIQUE, $migration::STATUS_INDEX, $migration::SENT_INDEX, $migration::NEWSLETTER_FK, $migration::USER_FK, $migration::AUTHOR_FK] as $name) {
            $this->assertLessThanOrEqual(64, strlen($name), $name);
        }
    }

    public function test_it_runs_again_on_a_complete_database(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_resumes_after_a_failure_halfway(): void
    {
        // État laissé par un échec au milieu : colonne et table des envois créées, destinataires sans index ni clé.
        Schema::dropIfExists('newsletter_recipients');
        Schema::create('newsletter_recipients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('newsletter_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_keeps_existing_users(): void
    {
        $this->migration()->down();
        User::factory()->create(['email' => 'eleve@example.com']);

        $this->migration()->up();

        $this->assertSchemaComplete();
        $this->assertDatabaseHas('users', ['email' => 'eleve@example.com', 'bulk_email_unsubscribed_at' => null]);
    }
}
