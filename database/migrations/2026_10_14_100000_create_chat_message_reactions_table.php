<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réactions par emoji aux messages des groupes : une seule réaction par personne et par message.
 *
 * Reprise sans erreur, quel que soit l'état de la base (MySQL n'annule pas une modification de structure) :
 * la table, l'index unique et les clés étrangères ne sont ajoutés que s'ils manquent. Noms d'index courts
 * (64 caractères au plus pour MySQL).
 */
return new class extends Migration
{
    public const string UNIQUE = 'chat_reactions_message_user_unique';

    public function up(): void
    {
        if (! Schema::hasTable('chat_message_reactions')) {
            Schema::create('chat_message_reactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chat_message_id');
                $table->unsignedBigInteger('user_id');
                // Clé de l'emoji (App\Enums\ChatReaction), jamais l'emoji lui-même.
                $table->string('emoji', 20);
                $table->timestamps();
            });
        }

        $hasUnique = collect(Schema::getIndexes('chat_message_reactions'))->contains(
            fn (array $index): bool => $index['columns'] === ['chat_message_id', 'user_id'] && $index['unique'],
        );

        if (! $hasUnique) {
            Schema::table('chat_message_reactions', fn (Blueprint $table) => $table->unique(['chat_message_id', 'user_id'], self::UNIQUE));
        }

        foreach (['chat_message_id' => 'chat_messages', 'user_id' => 'users'] as $column => $on) {
            $exists = collect(Schema::getForeignKeys('chat_message_reactions'))->contains(fn (array $key): bool => $key['columns'] === [$column]);

            if (! $exists) {
                Schema::table('chat_message_reactions', fn (Blueprint $table) => $table->foreign($column)->references('id')->on($on)->cascadeOnDelete());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reactions');
    }
};
