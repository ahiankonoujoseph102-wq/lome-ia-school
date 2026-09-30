<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Transmettre au fondateur » : questions qu'Ama n'a pas su traiter, transmises à Coach Joseph à la demande
 * du visiteur (prénom, moyen de réponse choisi : e-mail ou WhatsApp, question, page d'origine, court extrait
 * de la conversation). Page admin « Demandes transmises » (à traiter / traitée). Effacées après 90 jours,
 * comme les conversations d'Ama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_conversation_id')->nullable()->constrained()->nullOnDelete();
            // Empreinte HMAC de l'identifiant aléatoire du navigateur (limite de 3 demandes par jour).
            $table->string('visitor_hash', 64)->index();
            $table->string('first_name', 60);
            // « email » ou « whatsapp ».
            $table->string('channel', 10);
            $table->string('email', 191)->nullable();
            // Numéro WhatsApp au format international (+228…).
            $table->string('phone', 20)->nullable();
            $table->text('question');
            $table->string('page', 200)->nullable();
            $table->string('formation', 200)->nullable();
            // Court extrait de la conversation (textes déjà nettoyés des données personnelles).
            $table->text('excerpt')->nullable();
            // « chat » (carte de confirmation), « call » (fin d'un appel), « form » (formulaire de relève) ou « link »
            // (lien permanent « Poser ma question à Coach Joseph »).
            $table->string('source', 10);
            // « pending » (à traiter) ou « done » (traitée).
            $table->string('status', 10)->default('pending')->index();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_handoffs');
    }
};
