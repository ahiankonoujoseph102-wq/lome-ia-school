<?php

namespace App\Console\Commands;

use App\Packs\PackSalesTexts;
use Illuminate\Console\Command;

/**
 * Applique les textes de la page de vente /accompagnement (voir App\Packs\PackSalesTexts), après avoir enregistré
 * les anciens. Relançable. Retour : app:pack-sales-texts-revert.
 */
class ApplyPackSalesTexts extends Command
{
    protected $signature = 'app:pack-sales-texts-apply {--force : remplacer aussi un texte modifié dans l\'admin (il est enregistré d\'abord)}';

    protected $description = 'Applique les nouveaux textes de la page de vente des offres d\'accompagnement (anciens textes enregistrés d\'abord)';

    public function handle(): int
    {
        foreach (PackSalesTexts::apply((bool) $this->option('force')) as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }
}
