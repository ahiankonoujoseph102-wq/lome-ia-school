<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sondages des groupes (message de type « poll ») : question, choix et votes. Aucune table existante n'est modifiée.
 *
 * Reprise sans erreur, quel que soit l'état de la base (MySQL n'annule pas une modification de structure) :
 * chaque table, index et clé étrangère n'est ajouté que s'il manque. Noms courts (64 caractères au plus pour MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_polls')) {
            Schema::create('chat_polls', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chat_message_id');
                $table->string('question', 300);
                $table->boolean('allows_multiple')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_poll_options')) {
            Schema::create('chat_poll_options', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chat_poll_id');
                $table->string('label', 150);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_poll_votes')) {
            Schema::create('chat_poll_votes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chat_poll_id');
                $table->unsignedBigInteger('chat_poll_option_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        $this->index('chat_polls', ['chat_message_id'], 'chat_polls_message_unique', unique: true);
        $this->index('chat_poll_options', ['chat_poll_id', 'position'], 'chat_poll_options_poll_pos_idx');
        $this->index('chat_poll_votes', ['chat_poll_option_id', 'user_id'], 'chat_poll_votes_option_user_unique', unique: true);
        $this->index('chat_poll_votes', ['chat_poll_id', 'user_id'], 'chat_poll_votes_poll_user_idx');

        $this->foreign('chat_polls', 'chat_message_id', 'chat_messages', 'chat_polls_message_fk', cascade: true);
        $this->foreign('chat_polls', 'closed_by', 'users', 'chat_polls_closed_by_fk', cascade: false);
        $this->foreign('chat_poll_options', 'chat_poll_id', 'chat_polls', 'chat_poll_options_poll_fk', cascade: true);
        $this->foreign('chat_poll_votes', 'chat_poll_id', 'chat_polls', 'chat_poll_votes_poll_fk', cascade: true);
        $this->foreign('chat_poll_votes', 'chat_poll_option_id', 'chat_poll_options', 'chat_poll_votes_option_fk', cascade: true);
        $this->foreign('chat_poll_votes', 'user_id', 'users', 'chat_poll_votes_user_fk', cascade: true);
    }

    /**
     * @param  list<string>  $columns
     */
    private function index(string $table, array $columns, string $name, bool $unique = false): void
    {
        $exists = collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => $index['columns'] === $columns && (! $unique || $index['unique']),
        );

        if (! $exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name));
        }
    }

    private function foreign(string $table, string $column, string $on, string $name, bool $cascade): void
    {
        $exists = collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => $key['columns'] === [$column]);

        if (! $exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $on, $name, $cascade): void {
                $foreign = $blueprint->foreign($column, $name)->references('id')->on($on);
                $cascade ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_poll_votes');
        Schema::dropIfExists('chat_poll_options');
        Schema::dropIfExists('chat_polls');
    }
};
