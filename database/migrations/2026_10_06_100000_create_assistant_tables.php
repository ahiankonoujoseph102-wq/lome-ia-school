<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assistante « Ama » (Gemini) : conversations sans donnée personnelle (textes nettoyés, visiteur désigné
 * par une empreinte HMAC d'un identifiant aléatoire), incidents pour l'admin et compteur de demandes par
 * jour (journée de Google, qui recommence à minuit, heure du Pacifique). Effacées après 90 jours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            // Empreinte HMAC de l'identifiant aléatoire du navigateur (jamais l'adresse IP).
            $table->string('visitor_hash', 64)->index();
            $table->string('page', 200)->nullable();
            $table->string('formation', 200)->nullable();
            $table->unsignedInteger('messages_count')->default(0);
            $table->boolean('voice_used')->default(false);
            $table->boolean('fallback_shown')->default(false);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_conversation_id')->constrained()->cascadeOnDelete();
            // « user » (visiteur) ou « model » (Ama).
            $table->string('role', 10);
            $table->text('content');
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('assistant_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('detail', 300)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('assistant_usage', function (Blueprint $table) {
            $table->id();
            // Journée de Google (heure du Pacifique), au format AAAA-MM-JJ.
            $table->string('day', 10)->unique();
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_usage');
        Schema::dropIfExists('assistant_incidents');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
