<?php

namespace App\Newsletters;

use App\Enums\NewsletterAudience;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qui reçoit un envoi groupé : adresse e-mail confirmée, compte non bloqué, personne non désabonnée des
 * envois groupés (et, au choix, case « nouvelles formations » cochée). Vérifié une deuxième fois juste avant
 * chaque e-mail : une personne qui se désabonne ou dont le compte est bloqué entre-temps ne reçoit rien.
 */
class NewsletterRecipients
{
    /**
     * @return Builder<User>
     */
    public static function query(NewsletterAudience $audience = NewsletterAudience::All): Builder
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->whereNull('blocked_at')
            ->whereNull('bulk_email_unsubscribed_at')
            ->where('email', '!=', '')
            ->when($audience === NewsletterAudience::OptIn, fn (Builder $query) => $query->where('wants_newsletter', true));
    }

    public static function count(NewsletterAudience $audience = NewsletterAudience::All): int
    {
        return self::query($audience)->count();
    }

    public static function accepts(User $user, NewsletterAudience $audience): bool
    {
        return $user->hasConfirmedEmail()
            && ! $user->isBlocked()
            && ! $user->hasUnsubscribedFromBulkEmails()
            && filled($user->email)
            && ($audience === NewsletterAudience::All || $user->wants_newsletter);
    }
}
