<?php

namespace App\Console\Commands;

use App\LiveSessions\SessionManager;
use App\LiveSessions\SessionNotifier;
use App\LiveSessions\SessionRegistrar;
use App\Models\LiveSession;
use Illuminate\Console\Command;

/**
 * Suivi des sessions, lancé toutes les 5 minutes par le cron :
 * - rappels la veille et 1 h avant (e-mail et notification sur téléphone, selon les réglages de l'élève) ;
 * - places réservées sans paiement du supplément à temps : libérées pour la liste d'attente ;
 * - sessions terminées : annonce désépinglée du groupe.
 */
class ProcessLiveSessions extends Command
{
    protected $signature = 'app:process-live-sessions';

    protected $description = 'Rappels des sessions, places réservées expirées et annonces à désépingler';

    public function handle(SessionNotifier $notifier, SessionRegistrar $registrar, SessionManager $manager): int
    {
        $day = (int) config('live_sessions.reminders.day', 1440);
        $hour = (int) config('live_sessions.reminders.hour', 60);
        $reminders = 0;

        $sessions = LiveSession::query()
            ->whereNull('cancelled_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addMinutes($day))
            ->where(fn ($query) => $query->whereNull('day_reminder_sent_at')->orWhereNull('hour_reminder_sent_at'))
            ->with('formation')
            ->get();

        foreach ($sessions as $session) {
            $minutesLeft = now()->diffInMinutes($session->starts_at);

            if ($minutesLeft <= $hour) {
                // Le rappel de la veille n'a plus de sens : seul celui d'1 h part.
                if ($session->hour_reminder_sent_at === null) {
                    $session->forceFill(['hour_reminder_sent_at' => now(), 'day_reminder_sent_at' => $session->day_reminder_sent_at ?? now()])->save();
                    $reminders += $notifier->send($session, $notifier->audience($session), 'reminder_hour');
                }

                continue;
            }

            if ($session->day_reminder_sent_at === null) {
                $session->forceFill(['day_reminder_sent_at' => now()])->save();
                $reminders += $notifier->send($session, $notifier->audience($session), 'reminder_day');
            }
        }

        $expired = $registrar->releaseExpiredReservations();
        $unpinned = $manager->unpinFinished();

        $this->components->info("{$reminders} rappel(s), {$expired} place(s) réservée(s) libérée(s), {$unpinned} annonce(s) désépinglée(s).");

        return self::SUCCESS;
    }
}
