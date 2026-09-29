<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil de la personne qui réserve (particulier, centre de formation, entreprise) et nom de sa structure.
     * Les réservations déjà faites deviennent « particulier » (seul profil possible jusqu'ici).
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('requester_type', 20)->default('individual')->after('email');
            $table->string('organization_name', 150)->nullable()->after('requester_type');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn(['requester_type', 'organization_name']);
        });
    }
};
