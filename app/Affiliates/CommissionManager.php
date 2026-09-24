<?php

namespace App\Affiliates;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\PurchaseStatus;
use App\Mail\AffiliateMail;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Commissions d'affiliation.
 *
 * - Créée UNIQUEMENT à la validation du paiement par l'admin (jamais sur un accès provisoire),
 *   pour les formations et les suppléments de sessions, jamais sur ses propres achats.
 * - Disponible après le délai prévu (7 jours par défaut).
 * - Annulée si l'achat est refusé ou remboursé avant d'être versée ; déjà versée, elle est déduite
 *   des prochaines commissions (ligne négative : le solde peut devenir négatif).
 */
class CommissionManager
{
    public function createFor(Purchase $purchase): ?AffiliateCommission
    {
        if ($purchase->status !== PurchaseStatus::Validated || blank($purchase->affiliate_code)) {
            return null;
        }

        $affiliate = $purchase->affiliate()->with('user')->first();

        if ($affiliate === null || $affiliate->isSuspended() || $affiliate->user_id === $purchase->user_id) {
            return null;
        }

        $rate = (int) config('affiliate.rate', 10);
        $amount = intdiv($purchase->amount * $rate, 100);

        if ($amount <= 0) {
            return null;
        }

        $commission = AffiliateCommission::query()->firstOrCreate(
            ['purchase_id' => $purchase->id, 'type' => CommissionType::Sale],
            [
                'affiliate_id' => $affiliate->id,
                'base_amount' => $purchase->amount,
                'rate' => $rate,
                'amount' => $amount,
                'status' => CommissionStatus::Pending,
                'available_at' => now()->addDays((int) config('affiliate.availability_days', 7)),
            ],
        );

        if ($commission->wasRecentlyCreated) {
            Mail::to($affiliate->user)->send(new AffiliateMail($affiliate, 'commission_created', $commission));
        }

        return $commission;
    }

    /**
     * Achat refusé ou remboursé.
     */
    public function cancelFor(Purchase $purchase, string $reason): void
    {
        DB::transaction(function () use ($purchase, $reason): void {
            $commission = AffiliateCommission::query()
                ->where('purchase_id', $purchase->id)
                ->where('type', CommissionType::Sale)
                ->lockForUpdate()
                ->first();

            if ($commission === null || $commission->status === CommissionStatus::Cancelled) {
                return;
            }

            if ($commission->status === CommissionStatus::Paid) {
                // Déjà versée : déduite des prochaines commissions.
                AffiliateCommission::query()->firstOrCreate(
                    ['purchase_id' => $purchase->id, 'type' => CommissionType::Reversal],
                    [
                        'affiliate_id' => $commission->affiliate_id,
                        'base_amount' => $commission->base_amount,
                        'rate' => $commission->rate,
                        'amount' => -$commission->amount,
                        'status' => CommissionStatus::Available,
                        'available_at' => now(),
                    ],
                );

                return;
            }

            $withdrawal = $commission->withdrawal;

            $commission->forceFill([
                'status' => CommissionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'affiliate_withdrawal_id' => null,
            ])->save();

            // Commission comprise dans un retrait pas encore payé : le montant demandé est recalculé.
            if ($withdrawal instanceof AffiliateWithdrawal && $withdrawal->isOpen()) {
                $withdrawal->forceFill(['amount' => max(0, (int) $withdrawal->commissions()->sum('amount'))])->save();
            }
        });
    }

    /**
     * Commissions dont le délai est passé : disponibles, avec un e-mail par affilié.
     */
    public function releaseDue(): int
    {
        $due = AffiliateCommission::query()
            ->where('status', CommissionStatus::Pending)
            ->where('available_at', '<=', now())
            ->with('affiliate.user')
            ->get();

        $due->each(fn (AffiliateCommission $commission) => $commission->forceFill(['status' => CommissionStatus::Available])->save());

        $due->groupBy('affiliate_id')->each(function ($commissions): void {
            /** @var Affiliate $affiliate */
            $affiliate = $commissions->first()->affiliate;

            Mail::to($affiliate->user)->send(new AffiliateMail($affiliate, 'commission_available', amount: (int) $commissions->sum('amount')));
        });

        return $due->count();
    }
}
