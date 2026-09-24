<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leçons d'un module. La vidéo est repérée par son fournisseur (« youtube » aujourd'hui) et son identifiant.
     */
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_provider', 20)->nullable();
            $table->string('video_id', 100)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            // Leçon visible par tous les visiteurs depuis la page de la formation.
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_module_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
