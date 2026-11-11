<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidatures à la bourse « Créer des agents IA » (novembre 2026), envoyées sans compte avec la capture du paiement
 * des frais d'inscription. Reprenable : la table n'est créée que si elle manque ; index et clés aux noms courts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scholarship_applications')) {
            return;
        }

        Schema::create('scholarship_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('campaign', 40);
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->string('email');
            $table->string('gender', 10);
            $table->unsignedTinyInteger('age');
            $table->string('city', 120);
            $table->string('level', 20);
            $table->text('tools');
            $table->string('device', 20);
            $table->text('motivation');
            $table->text('first_agent');
            $table->boolean('available');
            $table->unsignedInteger('amount');
            $table->string('payment_method', 40);
            $table->string('payment_method_name', 80);
            $table->string('payment_number', 30)->nullable();
            $table->string('country', 5)->nullable();
            $table->string('proof_path');
            $table->string('proof_hash', 64)->unique('scholarship_proof_unique');
            $table->string('status', 20)->default('reserved');
            $table->boolean('refund_due')->default(false);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'scholarship_decider_fk')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'scholarship_user_fk')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases', indexName: 'scholarship_purchase_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign', 'status', 'gender'], 'scholarship_seats_idx');
            $table->index(['campaign', 'email'], 'scholarship_email_idx');
            $table->index(['campaign', 'phone'], 'scholarship_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_applications');
    }
};
