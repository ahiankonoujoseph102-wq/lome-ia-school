<?php

namespace App\Enums;

/**
 * Statut d'une demande de retrait des commissions.
 */
enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'En cours',
            self::Paid => 'Payé',
            self::Rejected => 'Refusé',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Requested => 'bg-gold-400/15 text-gold-200 ring-gold-400/40',
            self::Paid => 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/40',
            self::Rejected => 'bg-rose-400/15 text-rose-200 ring-rose-400/40',
        };
    }
}
