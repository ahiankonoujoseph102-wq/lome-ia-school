<?php

use App\Packs\PackDeadline;
use Illuminate\Database\Migrations\Migration;

/**
 * Fin des inscriptions des trois offres d'accompagnement : lundi 12 octobre 2026 à 16 h 00, heure de Lomé
 * (demande de Joseph). Voir App\Packs\PackDeadline::applyInitial() : seules les trois offres sans date sont
 * touchées (une date déjà réglée dans l'admin n'est jamais remplacée), aucun prix, texte ni achat modifié ;
 * relançable sans effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        PackDeadline::applyInitial();
    }

    public function down(): void
    {
        // Rien à défaire : la date se modifie ou se retire dans l'admin (« Formations et prix »).
    }
};
