<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ama plus rapide et plus attentive en appel :
 * - assistant_incidents.timings : durées mesurées d'une réponse (millisecondes, poids estimé de la demande, mode),
 *   sans aucune donnée personnelle (incident de type « response_timing ») ;
 * - assistant_messages.misheard : phrase dite en appel que Gemini n'a pas comprise (Ama a demandé de répéter) :
 *   elle ne compte pas dans les messages du visiteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_incidents', function (Blueprint $table) {
            $table->json('timings')->nullable()->after('detail');
        });

        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->boolean('misheard')->default(false)->after('unanswered');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_incidents', function (Blueprint $table) {
            $table->dropColumn('timings');
        });

        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->dropColumn('misheard');
        });
    }
};
