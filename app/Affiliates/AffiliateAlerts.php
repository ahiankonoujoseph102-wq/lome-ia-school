<?php

namespace App\Affiliates;

use App\Enums\CommissionType;
use App\Enums\PurchaseStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Affiliate;
use App\Models\Purchase;
use App\Support\Money;
use App\Support\PhoneNumber;

/**
 * Alertes d'affiliation pour l'admin. Ce sont des indices, pas des preuves : rien n'est bloqué
 * automatiquement, sauf l'auto-parrainage évident (le propre lien de l'acheteur n'est jamais enregistré).
 */
class AffiliateAlerts
{
    public function __construct(private AffiliateAccount $account) {}

    /**
     * Alertes sur un achat venu d'un lien d'affiliation (page « Paiements à vérifier »).
     *
     * @return list<array{level: string, text: string}>
     */
    public function forPurchase(Purchase $purchase): array
    {
        $affiliate = $purchase->affiliate;

        if ($affiliate === null) {
            return [];
        }

        $alerts = [];

        if ($this->ownNumbers($affiliate) !== [] && in_array($purchase->payer_phone, $this->ownNumbers($affiliate), true)) {
            $alerts[] = ['level' => 'danger', 'text' => 'Le numéro qui a payé est celui de l\'affilié (numéro WhatsApp ou numéro de retrait) : possible auto-parrainage.'];
        }

        if ($affiliate->isSuspended()) {
            $alerts[] = ['level' => 'info', 'text' => 'Affilié suspendu : aucune commission ne sera créée pour cet achat.'];
        }

        return [...$alerts, ...$this->forAffiliate($affiliate, withBalance: false)];
    }

    /**
     * Alertes sur un affilié (page « Retraits à payer » et liste des affiliés).
     *
     * @return list<array{level: string, text: string}>
     */
    public function forAffiliate(Affiliate $affiliate, bool $withBalance = true): array
    {
        $alerts = [];

        if ($affiliate->payout_phone !== null) {
            $sharing = Affiliate::query()
                ->where('payout_phone', $affiliate->payout_phone)
                ->whereKeyNot($affiliate->id)
                ->count();

            if ($sharing > 0) {
                $alerts[] = ['level' => 'danger', 'text' => 'Le numéro de retrait '.PhoneNumber::format($affiliate->payout_phone).' est aussi celui de '.$sharing.' autre'.($sharing > 1 ? 's' : '').' affilié'.($sharing > 1 ? 's' : '').'.'];
            }
        }

        $ownPaid = $this->salesPaidWithOwnNumbers($affiliate);

        if ($ownPaid > 0) {
            $alerts[] = ['level' => 'danger', 'text' => $ownPaid.' vente'.($ownPaid > 1 ? 's' : '').' payée'.($ownPaid > 1 ? 's' : '').' depuis le numéro WhatsApp ou le numéro de retrait de l\'affilié.'];
        }

        $threshold = (int) config('affiliate.alert_signups_without_purchase', 10);
        // Une inscription à une formation gratuite n'est pas suspecte : ces inscrits ne comptent pas.
        $withoutPurchase = $affiliate->referrals()
            ->whereDoesntHave('purchases', fn ($query) => $query->whereIn('status', PurchaseStatus::accessGranting()))
            ->whereDoesntHave('freeRegistrations')
            ->count();

        if ($threshold > 0 && $withoutPurchase >= $threshold) {
            $alerts[] = ['level' => 'warning', 'text' => $withoutPurchase.' inscriptions par son lien sans aucun achat.'];
        }

        if ($affiliate->payout_changed_at !== null) {
            $lastPaid = $affiliate->withdrawals()->where('status', WithdrawalStatus::Paid)->max('processed_at');

            if ($lastPaid === null || $affiliate->payout_changed_at->gt($lastPaid)) {
                $alerts[] = ['level' => 'warning', 'text' => 'Coordonnées de retrait modifiées le '.$affiliate->payout_changed_at->translatedFormat('j F Y à H\hi').'.'];
            }
        }

        if ($withBalance) {
            $balance = $this->account->balance($affiliate);

            if ($balance < 0) {
                $alerts[] = ['level' => 'warning', 'text' => 'Solde négatif ('.Money::fcfa($balance).') : un achat remboursé après le versement de sa commission sera déduit des prochaines commissions.'];
            }
        }

        if ($affiliate->isSuspended()) {
            $alerts[] = ['level' => 'info', 'text' => 'Affilié suspendu'.($affiliate->suspension_reason ? ' : '.$affiliate->suspension_reason : '').'.'];
        }

        return $alerts;
    }

    /**
     * @return list<string>
     */
    private function ownNumbers(Affiliate $affiliate): array
    {
        return array_values(array_filter([$affiliate->user->phone, $affiliate->payout_phone]));
    }

    private function salesPaidWithOwnNumbers(Affiliate $affiliate): int
    {
        $numbers = $this->ownNumbers($affiliate);

        if ($numbers === []) {
            return 0;
        }

        return $affiliate->commissions()
            ->where('type', CommissionType::Sale)
            ->whereHas('purchase', fn ($query) => $query->whereIn('payer_phone', $numbers))
            ->count();
    }
}
