<?php

namespace Tests\Feature\Chat;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migration du compteur d'e-mails et des annonces par e-mail reprend sans erreur quel que soit l'état de
 * la base (MySQL n'annule pas une modification de structure qui échoue au milieu).
 */
class EmailAnnouncementMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_100000_create_email_announcement_tables.php');
    }

    private function assertSchemaComplete(): void
    {
        $this->assertTrue(Schema::hasColumns('mail_daily_counts', ['id', 'day', 'sent', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasIndex('mail_daily_counts', ['day'], 'unique'));
        $this->assertTrue(Schema::hasColumns('chat_email_announcements', ['chat_group_id', 'chat_message_id', 'user_id', 'recipients', 'sent', 'failed', 'skipped']));
        $this->assertTrue(Schema::hasIndex('chat_email_announcements', ['chat_group_id', 'created_at']));

        $foreign = collect(Schema::getForeignKeys('chat_email_announcements'))->map(fn (array $key): string => $key['columns'][0].'>'.$key['foreign_table'])->sort()->values()->all();
        $this->assertSame(['chat_group_id>chat_groups', 'chat_message_id>chat_messages', 'user_id>users'], $foreign);
    }

    public function test_every_index_name_fits_the_mysql_limit_of_64_characters(): void
    {
        foreach (['mail_daily_counts', 'chat_email_announcements'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
            }

            foreach (Schema::getForeignKeys($table) as $key) {
                $this->assertLessThanOrEqual(64, strlen((string) $key['name']), (string) $key['name']);
            }
        }

        $migration = $this->migration();
        $this->assertLessThanOrEqual(64, strlen($migration::DAY_UNIQUE));
        $this->assertLessThanOrEqual(64, strlen($migration::GROUP_INDEX));
        $this->assertLessThanOrEqual(64, strlen('chat_email_ann_chat_message_id_fk'));
    }

    public function test_it_runs_again_on_a_complete_database(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $this->assertSchemaComplete();
    }

    public function test_it_resumes_after_a_failure_halfway(): void
    {
        // État laissé par un échec : tables créées, mais ni index ni clés étrangères.
        Schema::drop('chat_email_announcements');
        Schema::drop('mail_daily_counts');
        Schema::create('mail_daily_counts', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->unsignedInteger('sent')->default(0);
            $table->timestamps();
        });
        Schema::create('chat_email_announcements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('chat_group_id');
            $table->unsignedBigInteger('chat_message_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->timestamps();
        });

        $this->migration()->up();

        $this->assertSchemaComplete();
    }
}
