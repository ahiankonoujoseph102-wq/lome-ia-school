<?php

namespace App\Newsletters;

use App\Enums\NewsletterRecipientStatus;
use App\Enums\NewsletterStatus;
use App\Mail\MailQuota;
use App\Models\NewsletterRecipient;
use Illuminate\Support\Carbon;

/**
 * Plafond des envois groupés, pris sur le quota quotidien du fournisseur (Brevo : 300 par jour).
 *
 * Deux limites, la plus basse s'applique :
 * - le plafond des envois groupés réglé dans l'admin (200 par jour par défaut), jamais plus que
 *   « quota du jour − réserve » : la réserve reste toujours libre pour les codes de confirmation, mots de passe
 *   oubliés, inscriptions et paiements ;
 * - ce qui reste réellement aujourd'hui sans entamer cette réserve, en comptant tous les e-mails déjà partis du
 *   site et les annonces des groupes encore en file d'attente (elles passent avant).
 *
 * Les e-mails indispensables ne sont jamais bloqués : seul l'envoi groupé attend le lendemain.
 */
class NewsletterQuota
{
    public const int DEFAULT_DAILY_LIMIT = 200;

    /**
     * Nombre maximal d'e-mails mis en file d'attente à chaque passage (chaque minute) : l'envoi reste progressif.
     */
    public const int BATCH = 40;

    /**
     * Plafond quotidien des envois groupés, ramené si besoin sous « quota du jour − réserve ».
     */
    public static function dailyLimit(): int
    {
        $setting = (int) config('site.mail_quota.bulk_daily_limit', self::DEFAULT_DAILY_LIMIT);

        return max(0, min($setting, self::maximum()));
    }

    /**
     * Plus grand plafond permis : quota du fournisseur moins la réserve des e-mails indispensables.
     */
    public static function maximum(): int
    {
        return max(0, MailQuota::dailyLimit() - MailQuota::reserve());
    }

    /**
     * E-mails groupés partis aujourd'hui (journée de Lomé, UTC+0, comme le compteur du site).
     */
    public static function sentToday(): int
    {
        return NewsletterRecipient::query()
            ->where('status', NewsletterRecipientStatus::Sent->value)
            ->where('sent_at', '>=', self::startOfToday())
            ->count();
    }

    /**
     * E-mails groupés déjà dans la file d'attente ou en train de partir.
     */
    public static function inFlight(): int
    {
        return NewsletterRecipient::query()->whereIn('status', NewsletterRecipientStatus::inFlight())->count();
    }

    /**
     * Combien d'e-mails groupés peuvent encore partir aujourd'hui.
     */
    public static function availableToday(): int
    {
        $inFlight = self::inFlight();
        $bulk = self::dailyLimit() - self::sentToday() - $inFlight;
        $site = MailQuota::dailyLimit() - MailQuota::reserve() - MailQuota::sentToday() - MailQuota::pendingAnnouncements() - $inFlight;

        return max(0, min($bulk, $site));
    }

    /**
     * E-mails d'envois déjà lancés qui attendent encore (ils partent avant un nouvel envoi).
     */
    public static function waitingAhead(): int
    {
        return NewsletterRecipient::query()
            ->whereIn('status', NewsletterRecipientStatus::open())
            ->whereHas('newsletter', fn ($query) => $query->whereIn('status', [NewsletterStatus::Sending->value, NewsletterStatus::Paused->value]))
            ->count();
    }

    /**
     * Nombre de jours (aujourd'hui compris s'il reste de la place) pour envoyer $recipients e-mails après ceux
     * déjà en attente. Estimation : un jour où le reste du site envoie beaucoup d'e-mails, il en part moins.
     */
    public static function daysFor(int $recipients): ?int
    {
        if ($recipients <= 0) {
            return 0;
        }

        $perDay = self::dailyLimit();

        if ($perDay === 0) {
            return null;
        }

        $today = self::availableToday();
        $total = self::waitingAhead() + $recipients;

        return ($today > 0 ? 1 : 0) + (int) ceil(max(0, $total - $today) / $perDay);
    }

    public static function startOfToday(): Carbon
    {
        return Carbon::now('Africa/Lome')->startOfDay()->utc();
    }
}
