<?php

namespace App\Enums;

/**
 * Inscription à une session en présentiel.
 */
enum RegistrationStatus: string
{
    /** Place confirmée (gratuite, ou supplément payé : accès provisoire ou validé). */
    case Confirmed = 'confirmed';

    /** Place réservée en attendant le paiement du supplément ou sa vérification. */
    case Reserved = 'reserved';

    /** Session complète : en liste d'attente. */
    case Waitlisted = 'waitlisted';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Inscrit',
            self::Reserved => 'Place réservée',
            self::Waitlisted => 'Liste d\'attente',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * Occupe une place.
     */
    public function holdsSeat(): bool
    {
        return in_array($this, [self::Confirmed, self::Reserved], true);
    }

    /**
     * @return list<string>
     */
    public static function seatHolding(): array
    {
        return [self::Confirmed->value, self::Reserved->value];
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Confirmed => 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/30',
            self::Reserved => 'bg-gold-400/15 text-gold-200 ring-gold-400/40',
            self::Waitlisted => 'bg-electric-500/15 text-electric-200 ring-electric-400/40',
            self::Cancelled => 'bg-white/10 text-slate-300 ring-white/15',
        };
    }
}
