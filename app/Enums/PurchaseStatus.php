<?php

namespace App\Enums;

/**
 * Statut d'un achat. Seuls « accès provisoire » et « validé » ouvrent l'accès à la formation.
 */
enum PurchaseStatus: string
{
    /** Preuve reçue, accès ouvert en attendant la vérification (prix sous le seuil). */
    case Provisional = 'provisional';

    /** Preuve reçue, accès fermé jusqu'à la validation (prix égal ou supérieur au seuil). */
    case Pending = 'pending';

    case Validated = 'validated';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Provisional => 'Accès provisoire',
            self::Pending => 'En attente de validation',
            self::Validated => 'Validé',
            self::Rejected => 'Refusé',
            self::Refunded => 'Remboursé',
        };
    }

    /**
     * Paiement encore à vérifier par l'équipe.
     */
    public function isUnverified(): bool
    {
        return in_array($this, self::unverified(), true);
    }

    public function grantsAccess(): bool
    {
        return $this === self::Provisional || $this === self::Validated;
    }

    /**
     * Classes de la pastille de statut.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Provisional => 'bg-sky-400/15 text-sky-200 ring-sky-400/40',
            self::Pending => 'bg-gold-400/15 text-gold-200 ring-gold-400/40',
            self::Validated => 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/40',
            self::Rejected => 'bg-rose-400/15 text-rose-200 ring-rose-400/40',
            self::Refunded => 'bg-white/10 text-slate-300 ring-white/20',
        };
    }

    /**
     * @return list<self>
     */
    public static function unverified(): array
    {
        return [self::Provisional, self::Pending];
    }
}
