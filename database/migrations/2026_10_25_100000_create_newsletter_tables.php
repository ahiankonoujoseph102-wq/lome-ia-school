<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envoi d'e-mails groupés depuis l'admin (« Envoi d'e-mails ») : envois, destinataires et désabonnement.
 *
 * Uniquement des ajouts. Reprise sans erreur quel que soit l'état de la base (MySQL n'annule pas une
 * modification de structure qui échoue au milieu) : chaque colonne, table, index et clé étrangère n'est
 * ajouté que s'il manque. Noms d'index et de clés courts (64 caractères au plus pour MySQL).
 */
return new class extends Migration
{
    public const string RECIPIENT_UNIQUE = 'newsletter_rcpt_unique';

    public const string STATUS_INDEX = 'newsletter_rcpt_status_idx';

    public const string SENT_INDEX = 'newsletter_rcpt_sent_idx';

    public const string NEWSLETTER_FK = 'newsletter_rcpt_newsletter_fk';

    public const string USER_FK = 'newsletter_rcpt_user_fk';

    public const string AUTHOR_FK = 'newsletters_user_fk';

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'bulk_email_unsubscribed_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('bulk_email_unsubscribed_at')->nullable());
        }

        if (! Schema::hasTable('newsletters')) {
            Schema::create('newsletters', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('subject');
                $table->text('body');
                $table->string('image_path')->nullable();
                $table->string('video_url', 500)->nullable();
                $table->string('button_label')->nullable();
                $table->string('button_url', 500)->nullable();
                // Destinataires : « all » (adresse confirmée, non désabonnés) ou « optin » (case « nouvelles formations » cochée en plus).
                $table->string('audience', 20)->default('all');
                // Brouillon, en cours, en pause, annulé, terminé (App\Enums\NewsletterStatus).
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('recipients_count')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('newsletter_recipients')) {
            Schema::create('newsletter_recipients', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('newsletter_id');
                $table->unsignedBigInteger('user_id')->nullable();
                // En attente, en file, en cours, envoyé, échec, abandonné, annulé (App\Enums\NewsletterRecipientStatus).
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('retry_at')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->string('last_error')->nullable();
                $table->timestamps();
            });
        }

        $indexes = collect(Schema::getIndexes('newsletter_recipients'));

        if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['newsletter_id', 'user_id'] && $index['unique'])) {
            Schema::table('newsletter_recipients', fn (Blueprint $table) => $table->unique(['newsletter_id', 'user_id'], self::RECIPIENT_UNIQUE));
        }

        if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['status', 'newsletter_id'])) {
            Schema::table('newsletter_recipients', fn (Blueprint $table) => $table->index(['status', 'newsletter_id'], self::STATUS_INDEX));
        }

        if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['sent_at'])) {
            Schema::table('newsletter_recipients', fn (Blueprint $table) => $table->index('sent_at', self::SENT_INDEX));
        }

        $keys = [
            ['newsletter_recipients', 'newsletter_id', 'newsletters', 'cascade', self::NEWSLETTER_FK],
            ['newsletter_recipients', 'user_id', 'users', 'null', self::USER_FK],
            ['newsletters', 'user_id', 'users', 'null', self::AUTHOR_FK],
        ];

        foreach ($keys as [$tableName, $column, $on, $onDelete, $name]) {
            $exists = collect(Schema::getForeignKeys($tableName))->contains(fn (array $key): bool => $key['columns'] === [$column]);

            if (! $exists) {
                Schema::table($tableName, function (Blueprint $table) use ($column, $on, $onDelete, $name): void {
                    $foreign = $table->foreign($column, $name)->references('id')->on($on);
                    $onDelete === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_recipients');
        Schema::dropIfExists('newsletters');

        if (Schema::hasColumn('users', 'bulk_email_unsubscribed_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('bulk_email_unsubscribed_at'));
        }
    }
};
