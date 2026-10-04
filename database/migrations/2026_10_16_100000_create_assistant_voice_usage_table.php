<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteurs par jour de la voix naturelle d'Ama (modèles « TTS » de Gemini) : lectures avec la voix naturelle
 * (dont celles tirées de la mémoire, sans demande à Google), demandes envoyées à Google, refus de Google et passages
 * à la voix du téléphone.
 *
 * Reprise sans erreur, quel que soit l'état de la base (MySQL n'annule pas une modification de structure) : la table
 * et son index ne sont ajoutés que s'ils manquent. Nom d'index court (64 caractères au plus pour MySQL).
 */
return new class extends Migration
{
    public const string DAY_UNIQUE = 'assistant_voice_usage_day_unique';

    public function up(): void
    {
        if (! Schema::hasTable('assistant_voice_usage')) {
            Schema::create('assistant_voice_usage', function (Blueprint $table): void {
                $table->id();
                // Journée de Google (minuit, heure du Pacifique), comme assistant_usage.
                $table->date('day');
                $table->unsignedInteger('spoken')->default(0);
                $table->unsignedInteger('cached')->default(0);
                $table->unsignedInteger('google_requests')->default(0);
                $table->unsignedInteger('refusals')->default(0);
                $table->unsignedInteger('fallbacks')->default(0);
                $table->timestamps();
            });
        }

        $hasDayUnique = collect(Schema::getIndexes('assistant_voice_usage'))->contains(
            fn (array $index): bool => $index['columns'] === ['day'] && $index['unique'],
        );

        if (! $hasDayUnique) {
            Schema::table('assistant_voice_usage', fn (Blueprint $table) => $table->unique('day', self::DAY_UNIQUE));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_voice_usage');
    }
};
