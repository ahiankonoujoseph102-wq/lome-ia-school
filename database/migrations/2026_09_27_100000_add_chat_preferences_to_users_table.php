<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglages des notifications des groupes de discussion (profil de l'élève),
     * et date du dernier e-mail récapitulatif (au plus un par jour).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('chat_push_enabled')->default(true)->after('blocked_at');
            $table->boolean('chat_digest_enabled')->default(true)->after('chat_push_enabled');
            $table->timestamp('chat_digest_sent_at')->nullable()->after('chat_digest_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['chat_push_enabled', 'chat_digest_enabled', 'chat_digest_sent_at']);
        });
    }
};
