<?php

namespace App\Console\Commands;

use App\Packs\PackTexts;
use Illuminate\Console\Command;

/**
 * Applique les nouveaux textes des offres d'accompagnement (voir App\Packs\PackTexts). Relançable :
 * un texte déjà à jour ou modifié dans l'admin entre-temps n'est pas touché. Retour : app:pack-texts-revert.
 */
class ApplyPackTexts extends Command
{
    protected $signature = 'app:pack-texts-apply';

    protected $description = "Applique les nouveaux textes des offres d'accompagnement (descriptions et textes fixes de la page), sans écraser un texte modifié";

    public function handle(): int
    {
        foreach (PackTexts::apply() as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }
}
