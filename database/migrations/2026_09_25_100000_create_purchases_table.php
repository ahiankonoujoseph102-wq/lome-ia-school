<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Achats de formations. Montants en FCFA entiers.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            // Conservé si le compte est supprimé (obligations comptables, lutte contre la fraude).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('formation_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');

            // Moyen de paiement : « manual_mobile_money » aujourd'hui, FedaPay plus tard (App\Enums\PaymentGateway).
            $table->string('gateway', 30);
            // Clé du numéro utilisé (config site.payments.methods) et copie de ses informations au moment du paiement.
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_method_name', 60)->nullable();
            $table->string('payment_number', 30)->nullable();
            $table->string('payment_beneficiary', 120)->nullable();
            $table->string('country', 2)->nullable();

            // Preuve envoyée par l'élève.
            $table->string('payer_phone', 20)->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->string('transaction_id', 100)->nullable()->unique();
            // Capture sur le disque privé « payment_proofs », jamais accessible publiquement.
            $table->string('proof_path')->nullable();
            // Empreinte SHA-256 de la capture envoyée, pour refuser une capture déjà utilisée.
            $table->string('proof_hash', 64)->nullable()->unique();

            $table->string('status', 20)->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Prévus pour plus tard : affiliation (étape 8), remise, référence de la passerelle (FedaPay).
            $table->string('affiliate_code', 40)->nullable()->index();
            $table->unsignedInteger('discount_amount')->nullable();
            $table->string('gateway_reference', 100)->nullable()->index();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
