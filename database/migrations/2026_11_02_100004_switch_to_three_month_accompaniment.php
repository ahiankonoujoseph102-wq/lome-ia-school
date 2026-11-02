<?php

use App\Catalogue\Accompaniment;
use Illuminate\Database\Migrations\Migration;

/**
 * Novembre 2026 : « accompagnement à vie » devient un accompagnement de 3 mois après la formation, aussi dans les
 * textes déjà enregistrés dans l'admin (App\Catalogue\Accompaniment). Aucune modification de structure ; une seule
 * fois (réglage system.accompaniment_previous, qui garde les anciennes valeurs) ; relançable sans effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Accompaniment::applyToStoredSettings();
    }

    public function down(): void
    {
        // Rien à défaire automatiquement : les anciennes valeurs sont gardées dans system.accompaniment_previous.
    }
};
