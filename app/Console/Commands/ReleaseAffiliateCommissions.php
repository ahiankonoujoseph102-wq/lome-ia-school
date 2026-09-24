<?php

namespace App\Console\Commands;

use App\Affiliates\CommissionManager;
use Illuminate\Console\Command;

/**
 * Commissions d'affiliation dont le délai est passé (7 jours après la validation du paiement) :
 * elles deviennent disponibles et l'affilié est prévenu par e-mail. Lancée toutes les heures par le cron.
 */
class ReleaseAffiliateCommissions extends Command
{
    protected $signature = 'app:release-affiliate-commissions';

    protected $description = 'Rend disponibles les commissions d\'affiliation dont le délai est passé';

    public function handle(CommissionManager $commissions): int
    {
        $count = $commissions->releaseDue();
        $this->info($count.' commission(s) devenue(s) disponible(s).');

        return self::SUCCESS;
    }
}
