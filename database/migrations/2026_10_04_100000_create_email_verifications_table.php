<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vérification de l'adresse e-mail : un code à 6 chiffres et un lien, valables 30 minutes et à usage unique.
     * Code et lien ne sont jamais enregistrés en clair (empreintes seulement). Une ligne au plus par élève.
     *
     * Les comptes déjà inscrits sont considérés comme confirmés : aucune coupure pour eux.
     */
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash');
            $table->string('token_hash', 64)->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};
