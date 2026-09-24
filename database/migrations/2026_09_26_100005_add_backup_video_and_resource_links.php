<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vidéo de secours d'une leçon (autre fournisseur, ex. Google Drive si YouTube ne fonctionne pas),
     * et ressources pouvant être un lien Google Drive au lieu d'un fichier envoyé sur le site.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('backup_video_provider', 20)->nullable()->after('video_id');
            $table->string('backup_video_id', 100)->nullable()->after('backup_video_provider');
        });

        Schema::table('lesson_resources', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
            // Lien externe (Google Drive) : jamais affiché dans la page, ouvert via une redirection protégée.
            $table->string('url', 500)->nullable()->after('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['backup_video_provider', 'backup_video_id']);
        });

        Schema::table('lesson_resources', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
