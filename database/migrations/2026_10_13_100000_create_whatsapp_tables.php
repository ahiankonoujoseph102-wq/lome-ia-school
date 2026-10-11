<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent WhatsApp (API WhatsApp Business Cloud de Meta) : clients qui écrivent au numéro de l'API et messages
 * échangés (client, agent IA, équipe). Page admin « Conversations WhatsApp ». Effacés 90 jours après le dernier
 * message, comme les conversations d'Ama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            // Numéro WhatsApp du client tel que Meta le donne (chiffres seuls, avec l'indicatif : 22890123456).
            $table->string('wa_id', 20)->unique();
            // Nom choisi par le client dans son profil WhatsApp (peut être vide ou changer).
            $table->string('profile_name', 120)->nullable();
            // Dernier message reçu du client : la réponse libre n'est permise que pendant 24 h après lui.
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('messages_count')->default(0);
            // L'équipe a été appelée (demande du client, question sans réponse, limite, panne) et n'a pas encore traité.
            $table->timestamp('needs_human_at')->nullable()->index();
            $table->string('needs_human_reason', 200)->nullable();
            // Agent silencieux pour ce client jusqu'à cette date (réponse de l'équipe ou transmission à l'équipe).
            $table->timestamp('agent_paused_until')->nullable();
            // Agent arrêté pour ce client par l'admin, jusqu'à ce qu'il le relance.
            $table->boolean('agent_stopped')->default(false);
            // Dernier message du client déjà pris en compte par l'agent (deux messages rapprochés : une seule réponse
            // pour les deux, jamais deux réponses ni un message oublié).
            $table->unsignedBigInteger('last_answered_message_id')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
            // « client » (reçu), « agent » (réponse de l'IA) ou « team » (réponse écrite par l'équipe dans l'admin).
            $table->string('sender', 10);
            // Type WhatsApp : text, image, audio, video, document, sticker, location…
            $table->string('type', 20)->default('text');
            // Texte (codes secrets et mots de passe masqués) ; vide pour un média.
            $table->text('content')->nullable();
            // Identifiant du message chez Meta (« wamid… ») : un message reçu deux fois n'est traité qu'une fois.
            $table->string('wa_message_id', 191)->nullable()->unique();
            // Suivi d'un message envoyé : sent, delivered, read ou failed.
            $table->string('status', 12)->nullable();
            $table->string('error', 300)->nullable();
            // Réponse de l'agent qui dit ne pas avoir l'information (repère [INCONNU]).
            $table->boolean('unanswered')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['whatsapp_contact_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_contacts');
    }
};
