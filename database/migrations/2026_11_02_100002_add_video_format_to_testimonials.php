<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Format d'un témoignage vidéo (« horizontal », « vertical », vide = automatique). Ajout de colonne seulement, aucun
 * index ; reprenable (colonne déjà présente = rien à faire).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('testimonials', 'video_format')) {
            return;
        }

        Schema::table('testimonials', function (Blueprint $table): void {
            $table->string('video_format', 20)->nullable()->after('youtube_url');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('testimonials', 'video_format')) {
            Schema::table('testimonials', function (Blueprint $table): void {
                $table->dropColumn('video_format');
            });
        }
    }
};
