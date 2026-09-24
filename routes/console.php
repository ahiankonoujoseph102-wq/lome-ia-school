<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tâches planifiées (Hostinger : une seule ligne de cron, chaque minute)
|--------------------------------------------------------------------------
|
| * * * * * cd ~/chemin-du-site && php artisan schedule:run >> /dev/null 2>&1
|
| - File d'attente (e-mails, notifications sur téléphone) : traitée chaque minute, sans processus permanent.
| - E-mail récapitulatif des messages non lus : une fois par jour.
| - Notes vocales et vidéos des groupes : supprimées chaque nuit après la durée de conservation.
| - Sessions : rappels (veille et 1 h avant), places réservées expirées, annonces désépinglées (toutes les 5 min).
| - Affiliation : commissions devenues disponibles après le délai prévu (toutes les heures).
|
*/

Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('app:send-chat-digests')
    ->dailyAt((string) config('chat.digest_time', '18:00'))
    ->timezone('Africa/Lome')
    ->withoutOverlapping();

Schedule::command('app:expire-chat-media')
    ->dailyAt('03:00')
    ->timezone('Africa/Lome')
    ->withoutOverlapping();

Schedule::command('app:process-live-sessions')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('app:release-affiliate-commissions')
    ->hourly()
    ->withoutOverlapping();
