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
|
*/

Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('app:send-chat-digests')
    ->dailyAt((string) config('chat.digest_time', '18:00'))
    ->timezone('Africa/Lome')
    ->withoutOverlapping();
