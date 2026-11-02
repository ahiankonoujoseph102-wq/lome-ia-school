<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métiers où servent les compétences d'une formation (« careers », liste JSON, vide par défaut ; section masquée si
 * vide). Ajout de colonne seulement, aucun index ; reprenable (colonne déjà présente = rien à faire).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('formations', 'careers')) {
            return;
        }

        Schema::table('formations', function (Blueprint $table): void {
            $table->json('careers')->nullable()->after('audience');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('formations', 'careers')) {
            Schema::table('formations', function (Blueprint $table): void {
                $table->dropColumn('careers');
            });
        }
    }
};
