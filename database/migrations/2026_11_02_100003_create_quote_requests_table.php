<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de devis de la page « Entreprises et réalisations sur mesure » (novembre 2026). Reprenable : la table
 * n'est créée que si elle manque ; index au nom court.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quote_requests')) {
            return;
        }

        Schema::create('quote_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('organization', 160)->nullable();
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('service', 40);
            $table->text('message');
            $table->string('status', 20)->default('pending');
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users', indexName: 'quote_requests_handler_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'quote_requests_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
