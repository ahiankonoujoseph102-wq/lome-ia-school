<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étape 10 : tableau de bord admin.
 * - site_settings : réglages modifiables depuis l'admin (ils remplacent les valeurs des fichiers config/*.php).
 * - testimonials : témoignages gérés depuis l'admin.
 * - admin_activities : journal d'activité de l'admin et des modérateurs.
 * - promo_codes (+ formations concernées) : codes promo.
 * - purchases : code promo, prix avant remise, accès manuel (motif).
 * - formations.bonuses : bonus modifiables depuis l'admin (vides par défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            // Chemin de configuration remplacé, par exemple « site.contact » ou « affiliate.rate ».
            $table->string('key', 120)->unique();
            $table->longText('value');
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 120)->nullable();
            $table->string('formation', 160)->nullable();
            $table->text('text')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('image_path')->nullable();
            $table->string('youtube_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('description', 500);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description', 200)->nullable();
            // « percent » (valeur = pourcentage) ou « fixed » (valeur = montant en FCFA).
            $table->string('type', 10);
            $table->unsignedInteger('value');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->nullable();
            $table->boolean('all_formations')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('formation_promo_code', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->unique(['promo_code_id', 'formation_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('discount_amount')->constrained()->nullOnDelete();
            // Prix de la formation au moment de l'achat, avant la remise (le montant payé est « amount »).
            $table->unsignedInteger('original_amount')->nullable()->after('amount');
            // Accès manuel (paiement reçu hors du site) : motif saisi par l'admin.
            $table->text('manual_reason')->nullable()->after('rejection_reason');
        });

        Schema::table('formations', function (Blueprint $table) {
            $table->json('bonuses')->nullable()->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn('bonuses');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['original_amount', 'manual_reason']);
        });

        Schema::dropIfExists('formation_promo_code');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('admin_activities');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('site_settings');
    }
};
