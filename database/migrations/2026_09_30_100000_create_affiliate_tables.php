<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programme d'affiliation : affiliés, clics, commissions et retraits Mobile Money.
     */
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->timestamp('terms_accepted_at');
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            // Coordonnées de retrait Mobile Money.
            $table->string('payout_country', 5)->nullable();
            $table->string('payout_method', 40)->nullable();
            $table->string('payout_phone', 30)->nullable()->index();
            $table->string('payout_name')->nullable();
            $table->timestamp('payout_updated_at')->nullable();
            // Dernière modification de coordonnées déjà enregistrées (signalée à l'admin).
            $table->timestamp('payout_changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formation_id')->nullable()->constrained()->nullOnDelete();
            // Empreintes anonymes (jamais l'adresse IP en clair) : une même personne n'est comptée qu'une fois.
            $table->string('visitor_hash', 64);
            $table->string('ip_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['affiliate_id', 'visitor_hash']);
            $table->index(['affiliate_id', 'ip_hash']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referred_by_affiliate_id')->nullable()->after('session_email_enabled')->constrained('affiliates')->nullOnDelete();
        });

        Schema::create('affiliate_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('fee')->nullable();
            $table->unsignedInteger('net_amount')->nullable();
            $table->string('status', 20);
            // Copie des coordonnées au moment de la demande.
            $table->string('payout_country', 5);
            $table->string('payout_method', 40);
            $table->string('payout_method_name');
            $table->string('payout_phone', 30);
            $table->string('payout_name');
            $table->string('transaction_id', 100)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'status']);
            $table->index('status');
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            // « sale » : commission sur une vente ; « reversal » : déduction après un remboursement d'une commission déjà payée.
            $table->string('type', 20)->default('sale');
            $table->unsignedInteger('base_amount');
            $table->unsignedTinyInteger('rate');
            $table->integer('amount');
            $table->string('status', 20);
            $table->timestamp('available_at')->nullable();
            $table->foreignId('affiliate_withdrawal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 40)->nullable();
            $table->timestamps();

            $table->unique(['purchase_id', 'type']);
            $table->index(['affiliate_id', 'status']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_withdrawals');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_affiliate_id');
        });

        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliates');
    }
};
