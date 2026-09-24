<?php

namespace App\Affiliates;

use App\Mail\AffiliateAdminMail;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;

/**
 * Compte affilié : activation (acceptation des conditions), coordonnées de retrait, suspension.
 */
class AffiliateManager
{
    public function activate(User $user): Affiliate
    {
        if ($user->affiliate !== null) {
            return $user->affiliate;
        }

        try {
            $affiliate = Affiliate::query()->create([
                'user_id' => $user->id,
                'code' => Affiliate::generateCode(),
                'terms_accepted_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Double clic : le compte vient d'être créé par la requête précédente.
            $affiliate = Affiliate::query()->where('user_id', $user->id)->firstOrFail();
        }

        $user->setRelation('affiliate', $affiliate);

        return $affiliate;
    }

    /**
     * Enregistre les coordonnées de retrait. Une modification de coordonnées déjà enregistrées
     * est signalée à l'admin (e-mail et alerte sur la page « Retraits à payer »).
     *
     * @param  array{payout_country: string, payout_method: string, payout_phone: string, payout_name: string}  $details
     */
    public function updatePayout(Affiliate $affiliate, array $details): bool
    {
        $hadDetails = $affiliate->hasPayoutDetails();
        $before = $affiliate->only(['payout_country', 'payout_method', 'payout_phone', 'payout_name']);

        $affiliate->forceFill([...$details, 'payout_updated_at' => now()]);

        if (! $affiliate->isDirty(array_keys($details))) {
            return false;
        }

        $changed = $hadDetails;

        if ($changed) {
            $affiliate->forceFill(['payout_changed_at' => now()]);
        }

        $affiliate->save();

        if ($changed) {
            Mail::to((string) config('site.payments.admin_email'))->send(new AffiliateAdminMail($affiliate, 'payout_changed', previous: $before));
        }

        return true;
    }

    public function suspend(Affiliate $affiliate, ?string $reason): void
    {
        $affiliate->forceFill(['suspended_at' => now(), 'suspension_reason' => filled($reason) ? trim((string) $reason) : null])->save();
    }

    public function unsuspend(Affiliate $affiliate): void
    {
        $affiliate->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
    }
}
