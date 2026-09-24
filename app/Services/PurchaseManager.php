<?php

namespace App\Services;

use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Events\FormationAccessGranted;
use App\Events\FormationAccessRevoked;
use App\Events\PaymentRejected;
use App\Events\PaymentValidated;
use App\Models\Enrollment;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'un achat, commun à tous les moyens de paiement :
 * ouverture de l'accès, validation, refus, remboursement, blocage de compte.
 */
class PurchaseManager
{
    /**
     * Ouvre l'accès à la formation achetée (sans doublon).
     */
    public function grantAccess(Purchase $purchase): ?Enrollment
    {
        $user = $purchase->user;

        if (! $user) {
            return null;
        }

        $enrollment = $user->enroll($purchase->formation, EnrollmentSource::Purchase, $purchase);

        if ($enrollment->wasRecentlyCreated) {
            FormationAccessGranted::dispatch($enrollment);
        }

        return $enrollment;
    }

    /**
     * Paiement confirmé : l'accès devient définitif (ou s'ouvre s'il était en attente).
     */
    public function validate(Purchase $purchase, User $admin): Purchase
    {
        $purchase = DB::transaction(function () use ($purchase, $admin): Purchase {
            $purchase = $this->lockUnverified($purchase);

            $purchase->forceFill([
                'status' => PurchaseStatus::Validated,
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->grantAccess($purchase);

            return $purchase;
        });

        PaymentValidated::dispatch($purchase);

        return $purchase;
    }

    /**
     * Paiement refusé : l'accès éventuel est retiré immédiatement, et le compte peut être bloqué.
     */
    public function reject(Purchase $purchase, User $admin, string $reason, bool $blockUser = false): Purchase
    {
        $purchase = DB::transaction(function () use ($purchase, $admin, $reason): Purchase {
            $purchase = $this->lockUnverified($purchase);

            $this->revokeAccess($purchase);

            $purchase->forceFill([
                'status' => PurchaseStatus::Rejected,
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'rejection_reason' => trim($reason),
            ])->save();

            return $purchase;
        });

        if ($blockUser && $purchase->user) {
            $this->block($purchase->user);
        }

        PaymentRejected::dispatch($purchase);

        return $purchase;
    }

    /**
     * Achat remboursé : l'accès est retiré.
     */
    public function refund(Purchase $purchase, User $admin): Purchase
    {
        return DB::transaction(function () use ($purchase, $admin): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if (! $purchase->status->grantsAccess()) {
                throw ValidationException::withMessages(['purchase' => 'Seul un achat validé ou en accès provisoire peut être remboursé.']);
            }

            $this->revokeAccess($purchase);

            $purchase->forceFill([
                'status' => PurchaseStatus::Refunded,
                'verified_by' => $admin->id,
                'refunded_at' => now(),
            ])->save();

            return $purchase;
        });
    }

    /**
     * Bloque le compte : connexion impossible et sessions en cours fermées.
     */
    public function block(User $user): void
    {
        $user->forceFill(['blocked_at' => now(), 'remember_token' => null])->save();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    public function unblock(User $user): void
    {
        $user->forceFill(['blocked_at' => null])->save();
    }

    /**
     * Retire l'accès ouvert par cet achat (un accès donné manuellement par l'équipe n'est pas touché).
     */
    private function revokeAccess(Purchase $purchase): void
    {
        $enrollment = $purchase->enrollment()->first();

        if (! $enrollment) {
            return;
        }

        $enrollment->delete();

        if ($purchase->user) {
            FormationAccessRevoked::dispatch($purchase->user, $purchase->formation, $purchase);
        }
    }

    /**
     * Relit l'achat en le verrouillant, et vérifie qu'il est encore à vérifier (évite une double décision).
     */
    private function lockUnverified(Purchase $purchase): Purchase
    {
        $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

        if (! $locked->status->isUnverified()) {
            throw ValidationException::withMessages(['purchase' => 'Ce paiement a déjà été traité ('.mb_strtolower($locked->status->label()).').']);
        }

        return $locked;
    }
}
