<?php

namespace App\Mail;

use App\Models\ChatEmailAnnouncement;
use App\Models\MailDailyCount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quota quotidien d'e-mails du fournisseur (Brevo : 300 par jour sur l'offre gratuite), partagé par tous
 * les e-mails du site. Chaque e-mail réellement parti est compté (écouteur App\Listeners\CountSentEmail).
 *
 * Les e-mails ordinaires (codes de confirmation, paiements, inscriptions…) ne sont jamais bloqués ici : seules
 * les annonces de groupe par e-mail doivent laisser intacte la réserve réglée dans l'admin.
 */
class MailQuota
{
    public static function today(): string
    {
        return Carbon::now('Africa/Lome')->toDateString();
    }

    public static function dailyLimit(): int
    {
        return max(1, (int) config('site.mail_quota.daily_limit', 300));
    }

    public static function reserve(): int
    {
        return max(0, (int) config('site.mail_quota.reserve', 100));
    }

    /**
     * E-mails partis aujourd'hui (journée de Lomé, UTC+0).
     */
    public static function sentToday(): int
    {
        return (int) MailDailyCount::query()->where('day', self::today())->value('sent');
    }

    /**
     * E-mails d'annonce encore en file d'attente (ils partiront bientôt et compteront dans la journée).
     */
    public static function pendingAnnouncements(): int
    {
        return (int) ChatEmailAnnouncement::query()
            ->where('created_at', '>=', now()->subDay())
            ->get(['recipients', 'sent', 'failed', 'skipped'])
            ->sum(fn (ChatEmailAnnouncement $announcement): int => $announcement->pending());
    }

    /**
     * Nombre d'e-mails qu'une annonce peut encore envoyer aujourd'hui sans entamer la réserve.
     */
    public static function availableForAnnouncements(): int
    {
        return max(0, self::dailyLimit() - self::reserve() - self::sentToday() - self::pendingAnnouncements());
    }

    /**
     * Ajoute des e-mails partis au compteur du jour (mise à jour atomique, sans doublon de ligne).
     */
    public static function record(int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        $day = self::today();

        DB::table('mail_daily_counts')->insertOrIgnore(['day' => $day, 'sent' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mail_daily_counts')->where('day', $day)->increment('sent', $count, ['updated_at' => now()]);
    }
}
