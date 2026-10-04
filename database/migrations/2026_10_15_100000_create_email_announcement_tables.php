<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur des e-mails envoyés par jour (quota de Brevo) et annonces des groupes envoyées par e-mail.
 *
 * Reprise sans erreur, quel que soit l'état de la base (MySQL n'annule pas une modification de structure) :
 * chaque table, index et clé étrangère n'est ajouté que s'il manque. Noms d'index courts (64 caractères au
 * plus pour MySQL).
 */
return new class extends Migration
{
    public const string DAY_UNIQUE = 'mail_daily_counts_day_unique';

    public const string GROUP_INDEX = 'chat_email_ann_group_created_idx';

    public function up(): void
    {
        if (! Schema::hasTable('mail_daily_counts')) {
            Schema::create('mail_daily_counts', function (Blueprint $table): void {
                $table->id();
                // Jour de Lomé (UTC+0, comme la journée de Brevo).
                $table->date('day');
                $table->unsignedInteger('sent')->default(0);
                $table->timestamps();
            });
        }

        $hasDayUnique = collect(Schema::getIndexes('mail_daily_counts'))->contains(
            fn (array $index): bool => $index['columns'] === ['day'] && $index['unique'],
        );

        if (! $hasDayUnique) {
            Schema::table('mail_daily_counts', fn (Blueprint $table) => $table->unique('day', self::DAY_UNIQUE));
        }

        if (! Schema::hasTable('chat_email_announcements')) {
            Schema::create('chat_email_announcements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chat_group_id');
                $table->unsignedBigInteger('chat_message_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedInteger('recipients')->default(0);
                $table->unsignedInteger('sent')->default(0);
                $table->unsignedInteger('failed')->default(0);
                // Message supprimé avant l'envoi : e-mails abandonnés.
                $table->unsignedInteger('skipped')->default(0);
                $table->timestamps();
            });
        }

        $hasGroupIndex = collect(Schema::getIndexes('chat_email_announcements'))->contains(
            fn (array $index): bool => $index['columns'] === ['chat_group_id', 'created_at'],
        );

        if (! $hasGroupIndex) {
            Schema::table('chat_email_announcements', fn (Blueprint $table) => $table->index(['chat_group_id', 'created_at'], self::GROUP_INDEX));
        }

        $keys = [
            'chat_group_id' => ['chat_groups', 'cascade'],
            'chat_message_id' => ['chat_messages', 'null'],
            'user_id' => ['users', 'null'],
        ];

        foreach ($keys as $column => [$on, $onDelete]) {
            $exists = collect(Schema::getForeignKeys('chat_email_announcements'))->contains(fn (array $key): bool => $key['columns'] === [$column]);

            if (! $exists) {
                Schema::table('chat_email_announcements', function (Blueprint $table) use ($column, $on, $onDelete): void {
                    $foreign = $table->foreign($column, 'chat_email_ann_'.$column.'_fk')->references('id')->on($on);
                    $onDelete === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_email_announcements');
        Schema::dropIfExists('mail_daily_counts');
    }
};
