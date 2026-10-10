<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État « Formation terminée » d'une formation gratuite : interrupteur (éteint par défaut, la page reste exactement
 * comme avant), texte et destination interne du bouton affiché à la place de l'inscription (vides = « Découvrir
 * l'accompagnement » vers /accompagnement).
 *
 * Uniquement des ajouts de colonnes, aucun index. Reprise sans erreur (MySQL n'annule pas une modification de
 * structure) : chaque colonne n'est ajoutée que si elle manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('formations', 'free_ended')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->boolean('free_ended')->default(false)->after('presentation_video_format');
            });
        }

        if (! Schema::hasColumn('formations', 'free_ended_button_label')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->string('free_ended_button_label', 80)->nullable()->after('free_ended');
            });
        }

        if (! Schema::hasColumn('formations', 'free_ended_button_url')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->string('free_ended_button_url', 255)->nullable()->after('free_ended_button_label');
            });
        }
    }

    public function down(): void
    {
        foreach (['free_ended_button_url', 'free_ended_button_label', 'free_ended'] as $column) {
            if (Schema::hasColumn('formations', $column)) {
                Schema::table('formations', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
