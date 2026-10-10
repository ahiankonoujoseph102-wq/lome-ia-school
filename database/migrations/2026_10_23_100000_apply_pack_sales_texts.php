<?php

use App\Packs\PackSalesTexts;
use Illuminate\Database\Migrations\Migration;

/**
 * Page de vente /accompagnement : nouveaux textes des trois offres (accroche et points forts). Les textes d'avant
 * sont enregistrés d'abord (réglage « system.pack_sales_texts_saved »), puis remplacés, dans une transaction.
 * Relançable sans effet : un texte déjà à jour ou modifié dans l'admin n'est pas touché. Aucune modification de
 * structure : seules la colonne « description » des offres et la table des réglages sont écrites (jamais un prix,
 * une date, une liste de formations incluses ni un achat). Retour : « php artisan app:pack-sales-texts-revert ».
 */
return new class extends Migration
{
    public function up(): void
    {
        PackSalesTexts::apply();
    }

    public function down(): void
    {
        // Rien à défaire automatiquement : « php artisan app:pack-sales-texts-revert » remet les anciens textes.
    }
};
