<?php

namespace App\Console\Commands;

use App\Newsletters\NewsletterDispatcher;
use Illuminate\Console\Command;

/**
 * Envois d'e-mails groupés : met en file d'attente les prochains e-mails dans la limite du jour (lancé chaque
 * minute par le planificateur, juste avant le traitement de la file). Quand la limite du jour est atteinte, rien ne
 * part : la suite repart toute seule le lendemain.
 */
class SendNewsletters extends Command
{
    protected $signature = 'app:send-newsletters';

    protected $description = 'Mettre en file d\'attente les prochains e-mails des envois groupés, dans la limite du jour';

    public function handle(NewsletterDispatcher $dispatcher): int
    {
        $count = $dispatcher->run();

        $this->info($count.' e-mail(s) groupé(s) mis en file d\'attente.');

        return self::SUCCESS;
    }
}
