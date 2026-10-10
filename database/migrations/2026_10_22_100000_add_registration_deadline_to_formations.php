<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fin des inscriptions d'une offre d'accompagnement : formations.registration_ends_at (date et heure de Lomé).
 * Vide = aucune limite (comportement d'avant). Seules les offres (« pack ») s'en servent.
 *
 * Uniquement un ajout de colonne facultative (aucune donnée existante modifiée). Reprise sans erreur :
 * la colonne n'est ajoutée que si elle manque (MySQL n'annule pas une modification de structure). Aucun index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('formations', 'registration_ends_at')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->dateTime('registration_ends_at')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('formations', 'registration_ends_at')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->dropColumn('registration_ends_at');
            });
        }
    }
};
