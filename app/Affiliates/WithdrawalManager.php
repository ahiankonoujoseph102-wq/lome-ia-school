<?php

namespace App\Affiliates;

use App\Enums\CommissionStatus;
use App\Enums\WithdrawalStatus;
use App\Mail\AffiliateAdminMail;
use App\Mail\AffiliateMail;
use App\Models\Affiliate;
use App\Models\AffiliateWithdrawal;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Retraits des commissions par Mobile Money, validés manuellement par l'admin.
 *
 * - Une seule demande en cours à la fois, à partir du minimum prévu (2 000 FCFA par défaut).
 * - La demande regroupe tout le solde (commissions disponibles et déductions éventuelles).
 * - Payée : l'admin saisit les frais d'envoi réels, déduits du montant demandé, et l'identifiant de transaction.
 * - Refusée : le montant revient dans le solde.
 */
class WithdrawalManager
{
    public function request(Affiliate $affiliate): AffiliateWithdrawal
    {
        if ($affiliate->isSuspended()) {
            throw ValidationException::withMessages(['withdrawal' => 'Votre compte affilié est suspendu : les retraits ne sont pas possibles. Contactez-nous pour en savoir plus.']);
        }

        if (! $affiliate->hasPayoutDetails()) {
            throw ValidationException::withMessages(['withdrawal' => 'Renseignez d\'abord vos coordonnées de retrait Mobile Money.']);
        }

        $withdrawal = DB::transaction(function () use ($affiliate): AffiliateWithdrawal {
            // Verrou sur l'affilié : deux demandes simultanées ne peuvent pas passer toutes les deux.
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliate->id);

            if ($affiliate->withdrawals()->where('status', WithdrawalStatus::Requested)->exists()) {
                throw ValidationException::withMessages(['withdrawal' => 'Vous avez déjà une demande de retrait en cours. Vous pourrez en faire une autre dès qu\'elle aura été traitée.']);
            }

            $lines = $affiliate->commissions()
                ->where('status', CommissionStatus::Available)
                ->whereNull('affiliate_withdrawal_id')
                ->get();
            $amount = (int) $lines->sum('amount');

            if ($amount < AffiliateAccount::minimumWithdrawal()) {
                throw ValidationException::withMessages(['withdrawal' => 'Le retrait est possible à partir de '.Money::fcfa(AffiliateAccount::minimumWithdrawal()).' de solde. Votre solde : '.Money::fcfa($amount).'.']);
            }

            $withdrawal = $affiliate->withdrawals()->create([
                'amount' => $amount,
                'status' => WithdrawalStatus::Requested,
                'payout_country' => (string) $affiliate->payout_country,
                'payout_method' => (string) $affiliate->payout_method,
                'payout_method_name' => $affiliate->payoutMethod()->name ?? (string) $affiliate->payout_method,
                'payout_phone' => (string) $affiliate->payout_phone,
                'payout_name' => (string) $affiliate->payout_name,
            ]);

            $affiliate->commissions()->whereKey($lines->modelKeys())->update(['affiliate_withdrawal_id' => $withdrawal->id]);

            return $withdrawal;
        });

        Mail::to((string) config('site.payments.admin_email'))->send(new AffiliateAdminMail($affiliate, 'withdrawal_requested', withdrawal: $withdrawal));

        return $withdrawal;
    }

    public function markPaid(AffiliateWithdrawal $withdrawal, User $admin, int $fee, string $transactionId): AffiliateWithdrawal
    {
        $withdrawal = DB::transaction(function () use ($withdrawal, $admin, $fee, $transactionId): AffiliateWithdrawal {
            $withdrawal = $this->lockOpen($withdrawal);

            if ($fee < 0 || $fee >= $withdrawal->amount) {
                throw ValidationException::withMessages(['fee' => 'Les frais doivent être inférieurs au montant demandé ('.Money::fcfa($withdrawal->amount).').']);
            }

            $withdrawal->forceFill([
                'status' => WithdrawalStatus::Paid,
                'fee' => $fee,
                'net_amount' => $withdrawal->amount - $fee,
                'transaction_id' => trim($transactionId),
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ])->save();

            $withdrawal->commissions()->update(['status' => CommissionStatus::Paid]);

            return $withdrawal;
        });

        $withdrawal->load('affiliate.user');
        Mail::to($withdrawal->affiliate->user)->send(new AffiliateMail($withdrawal->affiliate, 'withdrawal_paid', withdrawal: $withdrawal));

        return $withdrawal;
    }

    public function reject(AffiliateWithdrawal $withdrawal, User $admin, string $reason): AffiliateWithdrawal
    {
        $withdrawal = DB::transaction(function () use ($withdrawal, $admin, $reason): AffiliateWithdrawal {
            $withdrawal = $this->lockOpen($withdrawal);

            $withdrawal->forceFill([
                'status' => WithdrawalStatus::Rejected,
                'rejection_reason' => trim($reason),
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ])->save();

            // Le montant revient dans le solde de l'affilié.
            $withdrawal->commissions()->update(['affiliate_withdrawal_id' => null]);

            return $withdrawal;
        });

        $withdrawal->load('affiliate.user');
        Mail::to($withdrawal->affiliate->user)->send(new AffiliateMail($withdrawal->affiliate, 'withdrawal_rejected', withdrawal: $withdrawal));

        return $withdrawal;
    }

    private function lockOpen(AffiliateWithdrawal $withdrawal): AffiliateWithdrawal
    {
        $locked = AffiliateWithdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

        if (! $locked->isOpen()) {
            throw ValidationException::withMessages(['withdrawal' => 'Ce retrait a déjà été traité ('.mb_strtolower($locked->status->label()).').']);
        }

        return $locked;
    }
}
