<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deuxième fournisseur de la voix naturelle d'Ama (Cloudflare Workers AI, MeloTTS) :
 * - table assistant_voice_quota : minutes d'audio fabriquées par jour et par fournisseur (journée de Cloudflare :
 *   0 h 00 UTC, soit 0 h 00 à Lomé), pour ne jamais dépasser l'offre gratuite ;
 * - colonne assistant_voice_events.provider : fournisseur de chaque mesure (vide pour les anciennes, toutes de Google).
 *
 * Reprise sans erreur, quel que soit l'état de la base (MySQL n'annule pas une modification de structure) : chaque
 * table, colonne et index n'est ajouté que s'il manque. Noms d'index courts (64 caractères au plus pour MySQL).
 */
return new class extends Migration
{
    public const string QUOTA_UNIQUE = 'ama_voice_quota_provider_day';

    public function up(): void
    {
        if (! Schema::hasTable('assistant_voice_quota')) {
            Schema::create('assistant_voice_quota', function (Blueprint $table): void {
                $table->id();
                // Fournisseur de la voix (« cloudflare »).
                $table->string('provider', 20);
                // Journée du fournisseur (Cloudflare : date UTC).
                $table->date('day');
                // Demandes réussies et durée totale de l'audio fabriqué (millisecondes).
                $table->unsignedInteger('requests')->default(0);
                $table->unsignedBigInteger('audio_ms')->default(0);
                $table->timestamps();
            });
        }

        $hasUnique = collect(Schema::getIndexes('assistant_voice_quota'))->contains(
            fn (array $index): bool => $index['name'] === self::QUOTA_UNIQUE || ($index['unique'] && $index['columns'] === ['provider', 'day']),
        );

        if (! $hasUnique) {
            Schema::table('assistant_voice_quota', fn (Blueprint $table) => $table->unique(['provider', 'day'], self::QUOTA_UNIQUE));
        }

        if (! Schema::hasColumn('assistant_voice_events', 'provider')) {
            Schema::table('assistant_voice_events', function (Blueprint $table): void {
                // Fournisseur de la voix : gemini, cloudflare ; vide = ancienne mesure (Google).
                $table->string('provider', 20)->nullable()->after('kind');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_voice_quota');

        if (Schema::hasColumn('assistant_voice_events', 'provider')) {
            Schema::table('assistant_voice_events', fn (Blueprint $table) => $table->dropColumn('provider'));
        }
    }
};
