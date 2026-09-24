<?php

namespace App\Enums;

/**
 * Statut d'une commission d'affiliation.
 */
enum CommissionStatus: string
{
    /** Paiement validé : la commission deviendra disponible après le délai prévu. */
    case Pending = 'pending';

    /** Retirable (comptée dans le solde). */
    case Available = 'available';

    /** Versée à l'affilié avec un retrait payé. */
    case Paid = 'paid';

    /** Achat refusé ou remboursé avant le versement. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Available => 'Disponible',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-gold-400/15 text-gold-200 ring-gold-400/40',
            self::Available => 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/40',
            self::Paid => 'bg-sky-400/15 text-sky-200 ring-sky-400/40',
            self::Cancelled => 'bg-white/10 text-slate-300 ring-white/20',
        };
    }
}
