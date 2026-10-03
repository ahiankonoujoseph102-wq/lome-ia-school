<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscriptions gratuites : options d'inscription à places limitées (présentiel, en ligne…) et inscrits.
 * Uniquement pour les formations à 0 FCFA ; rien ne change pour les formations payantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table): void {
            // Date et lieu de l'événement d'une formation gratuite (affichés sur la page et dans l'e-mail).
            $table->date('event_date')->nullable()->after('bonuses');
            $table->string('event_location')->nullable()->after('event_date');
        });

        Schema::create('free_registration_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('schedule', 160)->nullable();
            $table->string('mode', 20);
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['formation_id', 'position']);
        });

        Schema::create('free_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('free_registration_option_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Une seule inscription (donc une seule option) par personne et par formation.
            $table->unique(['formation_id', 'user_id']);
            $table->index(['free_registration_option_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_registrations');
        Schema::dropIfExists('free_registration_options');

        Schema::table('formations', function (Blueprint $table): void {
            $table->dropColumn(['event_date', 'event_location']);
        });
    }
};
