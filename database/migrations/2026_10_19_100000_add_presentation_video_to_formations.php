<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vidéo de présentation d'une formation gratuite (lien YouTube collé par l'admin, interrupteur, format).
 * Vide et éteinte par défaut : la page reste exactement comme avant.
 *
 * Reprise sans erreur (MySQL n'annule pas une modification de structure) : chaque colonne n'est ajoutée
 * que si elle manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('formations', 'presentation_video_url')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->string('presentation_video_url', 500)->nullable()->after('event_location');
            });
        }

        if (! Schema::hasColumn('formations', 'presentation_video_enabled')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->boolean('presentation_video_enabled')->default(false)->after('presentation_video_url');
            });
        }

        if (! Schema::hasColumn('formations', 'presentation_video_format')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->string('presentation_video_format', 20)->nullable()->after('presentation_video_enabled');
            });
        }
    }

    public function down(): void
    {
        foreach (['presentation_video_format', 'presentation_video_enabled', 'presentation_video_url'] as $column) {
            if (Schema::hasColumn('formations', $column)) {
                Schema::table('formations', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
