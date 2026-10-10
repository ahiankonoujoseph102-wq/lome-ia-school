<?php

use App\Packs\PackPageTitle;
use Illuminate\Database\Migrations\Migration;

/**
 * Page /accompagnement : titre et sous-titre validés lors de la refonte (App\Packs\PackPageTitle). Aucune modification
 * de structure : seul le réglage « site.packs_page » est écrit, et seulement ses deux clés « title » et « subtitle »
 * quand elles valent encore un ancien texte connu. Relançable sans effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        PackPageTitle::apply();
    }

    public function down(): void
    {
        // Rien à défaire automatiquement : l'ancienne valeur est gardée dans le réglage « system.packs_page_title_saved ».
    }
};
