<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réponses d'Ama où elle a dit qu'elle n'avait pas l'information : liste « Questions sans réponse » de l'admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->boolean('unanswered')->default(false)->index()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->dropIndex(['unanswered']);
            $table->dropColumn('unanswered');
        });
    }
};
