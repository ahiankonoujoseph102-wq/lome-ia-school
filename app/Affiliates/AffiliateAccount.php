<?php

namespace App\Affiliates;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\WithdrawalStatus;
use App\Models\Affiliate;

/**
 * Statistiques et solde d'un affilié.
 *
 * - En attente : commissions pas encore disponibles.
 * - Disponibles : commissions retirables pas encore demandées.
 * - Solde : disponibles moins les déductions (remboursements d'achats déjà versés) ; peut être négatif.
 * - En cours de retrait : montant de la demande de retrait en cours.
 * - Payées : total reçu par les retraits payés (avant frais d'envoi).
 */
class AffiliateAccount
{
    /**
     * @return array{clicks: int, signups: int, sales: int, pending: int, available: int, deductions: int, balance: int, requested: int, paid: int}
     */
    public function summary(Affiliate $affiliate): array
    {
        $unreserved = $affiliate->commissions()
            ->where('status', CommissionStatus::Available)
            ->whereNull('affiliate_withdrawal_id');

        $available = (int) (clone $unreserved)->where('type', CommissionType::Sale)->sum('amount');
        $deductions = (int) (clone $unreserved)->where('type', CommissionType::Reversal)->sum('amount');

        return [
            'clicks' => $affiliate->clicks()->count(),
            'signups' => $affiliate->referrals()->count(),
            'sales' => $affiliate->commissions()->where('type', CommissionType::Sale)->where('status', '!=', CommissionStatus::Cancelled)->count(),
            'pending' => (int) $affiliate->commissions()->where('status', CommissionStatus::Pending)->sum('amount'),
            'available' => $available,
            'deductions' => $deductions,
            'balance' => $available + $deductions,
            'requested' => (int) $affiliate->withdrawals()->where('status', WithdrawalStatus::Requested)->sum('amount'),
            'paid' => (int) $affiliate->withdrawals()->where('status', WithdrawalStatus::Paid)->sum('amount'),
        ];
    }

    public function balance(Affiliate $affiliate): int
    {
        return (int) $affiliate->commissions()
            ->where('status', CommissionStatus::Available)
            ->whereNull('affiliate_withdrawal_id')
            ->sum('amount');
    }

    public function hasOpenWithdrawal(Affiliate $affiliate): bool
    {
        return $affiliate->withdrawals()->where('status', WithdrawalStatus::Requested)->exists();
    }

    public static function minimumWithdrawal(): int
    {
        return (int) config('affiliate.min_withdrawal', 2000);
    }
}
