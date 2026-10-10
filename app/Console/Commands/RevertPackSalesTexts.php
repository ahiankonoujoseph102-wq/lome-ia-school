<?php

namespace App\Console\Commands;

use App\Packs\PackSalesTexts;
use Illuminate\Console\Command;

/**
 * Remet les textes de la page /accompagnement d'avant la refonte (enregistrés par app:pack-sales-texts-apply ou la
 * migration). Relançable : un texte déjà ancien ou modifié dans l'admin depuis n'est pas touché.
 */
class RevertPackSalesTexts extends Command
{
    protected $signature = 'app:pack-sales-texts-revert';

    protected $description = 'Remet les textes de la page des offres d\'accompagnement d\'avant la refonte';

    public function handle(): int
    {
        foreach (PackSalesTexts::revert() as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }
}
