<?php

namespace App\Newsletters;

use App\Models\Newsletter;
use App\Models\NewsletterRecipient;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Désabonnement des envois groupés : lien en bas de chaque e-mail (un clic, sans connexion) et en-tête
 * « List-Unsubscribe » reconnu par Gmail. Seuls les envois groupés de l'admin s'arrêtent : codes, mots de passe,
 * inscriptions, paiements, sessions et annonces des groupes continuent. Réabonnement depuis « Mon espace >
 * Notifications » ou la page de désabonnement.
 */
class BulkEmailSubscription
{
    /**
     * Lien signé (sans date d'expiration : un vieil e-mail doit pouvoir désabonner).
     */
    public static function unsubscribeUrl(User $user, ?Newsletter $newsletter = null): string
    {
        return URL::signedRoute('newsletter.unsubscribe', array_filter([
            'user' => $user->id,
            'envoi' => $newsletter?->id,
        ]));
    }

    public static function resubscribeUrl(User $user): string
    {
        return URL::signedRoute('newsletter.resubscribe', ['user' => $user->id]);
    }

    public static function unsubscribe(User $user, ?int $newsletterId = null): void
    {
        if (! $user->hasUnsubscribedFromBulkEmails()) {
            $user->forceFill(['bulk_email_unsubscribed_at' => now()])->save();
        }

        if ($newsletterId !== null) {
            NewsletterRecipient::query()
                ->where('newsletter_id', $newsletterId)
                ->where('user_id', $user->id)
                ->whereNull('unsubscribed_at')
                ->update(['unsubscribed_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function resubscribe(User $user): void
    {
        if ($user->hasUnsubscribedFromBulkEmails()) {
            $user->forceFill(['bulk_email_unsubscribed_at' => null])->save();
        }
    }
}
