<?php

use App\Packs\PackSetup;
use Illuminate\Database\Migrations\Migration;

/**
 * Trois offres d'accompagnement (niveaux 1, 2 et 3) et le Groupe VIP, créés masqués : Joseph les vérifie
 * puis les publie dans l'admin. Même création que la commande « php artisan app:create-packs » (relançable
 * sans doublon, compte rendu des formations rattachées et introuvables).
 *
 * Tout est écrit dans une seule transaction ; une offre déjà présente n'est jamais recréée ni modifiée.
 * Le FormationSeeder n'est pas relancé ; aucun achat n'est touché.
 */
return new class extends Migration
{
    public function up(): void
    {
        PackSetup::run();
    }

    public function down(): void
    {
        // Rien : les offres ont pu être vendues ; elles se masquent dans l'admin.
    }
};
