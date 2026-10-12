<?php

use App\Assistant\NaturalVoice;
use App\Jobs\PrepareAmaVoicePhrases;
use App\Models\AssistantConversation;
use App\Models\AssistantHandoff;
use App\Models\AssistantIncident;
use App\Models\AssistantUsage;
use App\Models\AssistantVoiceEvent;
use App\Models\AssistantVoiceQuota;
use App\Models\AssistantVoiceUsage;
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
| - Consultations gratuites : rappels (veille et 1 h avant) et suppression des anciens rendez-vous (toutes les 5 min).
| - Illustration d'« Agents IA » : posée dès le 8 octobre 2026 (fin du direct), si la formation n'a pas d'image (chaque nuit).
| - Assistante Ama : conversations, demandes transmises au fondateur, incidents, compteurs et mesures de la voix effacés
|   après 90 jours (chaque nuit).
| - Voix naturelle d'Ama : phrases écrites à l'avance préparées en mémoire si l'une manque (chaque heure, sans effet sinon).
| - Envois d'e-mails groupés de l'admin : prochains e-mails mis en file dans la limite du jour (chaque minute, juste
|   avant la file d'attente) ; au-delà de la limite, la suite repart toute seule le lendemain.
|
*/

Schedule::command('app:send-newsletters')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('app:apply-formation-illustrations')
    ->dailyAt('00:05')
    ->timezone('Africa/Lome')
    ->withoutOverlapping();

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

Schedule::command('app:process-consultations')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [AssistantConversation::class, AssistantHandoff::class, AssistantIncident::class, AssistantUsage::class, AssistantVoiceUsage::class, AssistantVoiceEvent::class, AssistantVoiceQuota::class]])
    ->dailyAt('03:30')
    ->timezone('Africa/Lome')
    ->withoutOverlapping();

Schedule::job(new PrepareAmaVoicePhrases)
    ->hourly()
    ->when(fn (): bool => app(NaturalVoice::class)->enabled());
