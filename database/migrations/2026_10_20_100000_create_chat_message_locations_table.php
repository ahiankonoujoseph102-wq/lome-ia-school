<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieux partagés dans les groupes (message de type « location ») : lien Google Maps, nom et adresse facultatifs,
 * coordonnées si la position du téléphone a été envoyée. Aucune table existante n'est modifiée.
 *
 * Reprise sans erreur, quel que soit l'état de la base (MySQL n'annule pas une modification de structure) :
 * table, index et clé étrangère ajoutés seulement s'ils manquent. Noms courts (64 caractères au plus pour MySQL).
 */
return new class extends Migration
{
    public const string MESSAGE_UNIQUE = 'chat_locations_message_unique';

    public const string MESSAGE_FOREIGN = 'chat_locations_message_fk';

    public function up(): void
    {
        if (! Schema::hasTable('chat_message_locations')) {
            Schema::create('chat_message_locations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('chat_message_id');
                $table->string('name', 120)->nullable();
                $table->string('address', 200)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('maps_url', 500);
                $table->timestamps();
            });
        }

        $hasUnique = collect(Schema::getIndexes('chat_message_locations'))->contains(
            fn (array $index): bool => $index['columns'] === ['chat_message_id'] && $index['unique'],
        );

        if (! $hasUnique) {
            Schema::table('chat_message_locations', fn (Blueprint $table) => $table->unique('chat_message_id', self::MESSAGE_UNIQUE));
        }

        $hasForeign = collect(Schema::getForeignKeys('chat_message_locations'))->contains(
            fn (array $key): bool => $key['columns'] === ['chat_message_id'],
        );

        if (! $hasForeign) {
            Schema::table('chat_message_locations', fn (Blueprint $table) => $table->foreign('chat_message_id', self::MESSAGE_FOREIGN)
                ->references('id')->on('chat_messages')->cascadeOnDelete());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_locations');
    }
};
