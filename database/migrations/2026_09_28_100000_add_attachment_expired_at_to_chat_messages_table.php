<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notes vocales et vidéos supprimées automatiquement après la durée de conservation :
     * le message reste, avec la mention « Fichier expiré ».
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('attachment_expired_at')->nullable()->after('duration_seconds');
            $table->index(['type', 'created_at']);
        });

        // Règles par défaut déjà enregistrées : ajout de la ligne sur la suppression des notes vocales et vidéos.
        $line = "• Utilisez le bouton « Signaler » si un message pose problème : l'équipe s'en occupe.";

        DB::table('chat_groups')
            ->where('rules', 'like', '%'.$line.'%')
            ->where('rules', 'not like', '%supprimées automatiquement%')
            ->orderBy('id')
            ->get(['id', 'rules'])
            ->each(function (object $group) use ($line): void {
                DB::table('chat_groups')->where('id', $group->id)->update([
                    'rules' => str_replace($line, $line."\n• Les notes vocales et les vidéos sont supprimées automatiquement 12 mois après leur envoi : enregistrez celles que vous voulez garder.", (string) $group->rules),
                    'rules_updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
            $table->dropColumn('attachment_expired_at');
        });
    }
};
