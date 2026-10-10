<?php

namespace App\Console\Commands;

use App\Packs\PackSetup;
use Illuminate\Console\Command;

/**
 * Crée les trois offres d'accompagnement et le Groupe VIP s'ils n'existent pas encore (masqués), puis affiche
 * les formations rattachées à chaque offre et celles qui n'ont pas été trouvées. Relançable sans doublon :
 * une offre déjà créée n'est jamais modifiée (sa liste se règle dans l'admin).
 */
class CreatePacks extends Command
{
    protected $signature = 'app:create-packs';

    protected $description = 'Crée les offres d\'accompagnement et le Groupe VIP (masqués), sans doublon, et affiche le compte rendu';

    public function handle(): int
    {
        $report = PackSetup::run();

        $this->components->info('Groupe VIP : '.$report['vip'].'.');

        foreach ($report['packs'] as $pack) {
            $this->newLine();
            $this->components->info($pack['title'].' : '.$pack['status'].'.');
            $this->line('  Rattachées : '.($pack['attached'] === [] ? 'aucune' : implode(' ; ', $pack['attached'])));

            if ($pack['missing'] !== []) {
                $this->components->warn('Introuvables dans la base (seulement citées dans la description) : '.implode(' ; ', $pack['missing']));
            }
        }

        return self::SUCCESS;
    }
}
