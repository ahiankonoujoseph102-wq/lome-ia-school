<?php

namespace App\Console\Commands;

use App\Packs\PackTexts;
use Illuminate\Console\Command;

/**
 * Remet les anciens textes des offres d'accompagnement (voir App\Packs\PackTexts). Relançable :
 * un texte déjà ancien ou modifié dans l'admin entre-temps n'est pas touché.
 */
class RevertPackTexts extends Command
{
    protected $signature = 'app:pack-texts-revert';

    protected $description = "Remet les anciens textes des offres d'accompagnement, sans écraser un texte modifié";

    public function handle(): int
    {
        foreach (PackTexts::revert() as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }
}
