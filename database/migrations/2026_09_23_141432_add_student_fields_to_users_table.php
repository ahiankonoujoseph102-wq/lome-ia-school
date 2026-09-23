<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Numéro WhatsApp au format international (ex. +22890000000).
            // Obligatoire à l'inscription ; nullable en base pour les comptes admin créés en ligne de commande.
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('role', 20)->default('student')->index()->after('phone');
            $table->string('avatar_path')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropIndex(['role']);
            $table->dropColumn(['phone', 'role', 'avatar_path']);
        });
    }
};
