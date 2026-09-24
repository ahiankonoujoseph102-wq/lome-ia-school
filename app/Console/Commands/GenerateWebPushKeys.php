<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Crée la paire de clés VAPID des notifications sur téléphone, à copier dans .env (jamais dans le code).
 */
class GenerateWebPushKeys extends Command
{
    protected $signature = 'app:webpush-keys';

    protected $description = 'Créer les clés des notifications sur téléphone (Web Push), à copier dans .env';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->components->info('Copiez ces deux lignes dans le fichier .env du serveur (jamais dans le code) :');
        $this->line('WEBPUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_PRIVATE_KEY='.$keys['privateKey']);
        $this->components->warn('Gardez la clé privée secrète. Si vous la changez, chaque élève devra réactiver les notifications.');

        return self::SUCCESS;
    }
}
