<?php

namespace App\Console\Commands;

use App\Support\FormationIllustrations;
use Illuminate\Console\Command;

/**
 * Pose l'illustration d'« Agents IA » dès le 8 octobre 2026 (fin du direct), si la formation n'a toujours pas
 * d'image. Lancée chaque nuit par le cron ; sans effet avant la date, ni une fois l'illustration traitée.
 * Ne touche jamais aux 16 autres formations : une image retirée dans l'admin n'est pas remise.
 */
class ApplyFormationIllustrations extends Command
{
    protected $signature = 'app:apply-formation-illustrations';

    protected $description = 'Pose l\'illustration d\'« Agents IA » à partir du 8 octobre 2026 (si la formation n\'a pas d\'image)';

    public function handle(): int
    {
        $this->info(FormationIllustrations::applyAgentsWhenDue()
            ? 'Illustration d\'« Agents IA » posée.'
            : 'Rien à faire.');

        return self::SUCCESS;
    }
}
