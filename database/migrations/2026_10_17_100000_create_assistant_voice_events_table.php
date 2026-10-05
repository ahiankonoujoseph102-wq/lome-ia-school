<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesure de la voix naturelle d'Ama : une ligne par demande envoyée à Google (temps mis par Google, réussite ou
 * cause de l'échec) et par passage à la voix du téléphone (cause exacte, décidée par le serveur ou par le navigateur).
 * Aucun texte n'est enregistré : seulement des durées, des longueurs et des codes de cause.
 *
 * Reprise sans erreur, quel que soit l'état de la base (MySQL n'annule pas une modification de structure) : la table
 * et son index ne sont ajoutés que s'ils manquent. Nom d'index court (64 caractères au plus pour MySQL).
 */
return new class extends Migration
{
    public const string DAY_INDEX = 'ama_voice_events_day_kind';

    public function up(): void
    {
        if (! Schema::hasTable('assistant_voice_events')) {
            Schema::create('assistant_voice_events', function (Blueprint $table): void {
                $table->id();
                // Journée de Google (minuit, heure du Pacifique), comme assistant_usage.
                $table->date('day');
                // google : demande envoyée à Google ; fallback : passage à la voix du téléphone ; memory : mémoire.
                $table->string('kind', 20);
                // Cause (code de NaturalVoice::CAUSES) ; vide pour une demande à Google réussie.
                $table->string('cause', 40)->nullable();
                // Origine d'une demande à Google : site (visiteur), preview (bouton « Écouter »), prepare (préparation).
                $table->string('source', 20)->nullable();
                // Temps mis par Google, ou attente du téléphone avant d'abandonner (millisecondes).
                $table->unsignedInteger('duration_ms')->nullable();
                // Longueur du texte (caractères), jamais le texte lui-même.
                $table->unsignedInteger('chars')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        $hasIndex = collect(Schema::getIndexes('assistant_voice_events'))->contains(
            fn (array $index): bool => $index['name'] === self::DAY_INDEX || $index['columns'] === ['day', 'kind'],
        );

        if (! $hasIndex) {
            Schema::table('assistant_voice_events', fn (Blueprint $table) => $table->index(['day', 'kind'], self::DAY_INDEX));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_voice_events');
    }
};
