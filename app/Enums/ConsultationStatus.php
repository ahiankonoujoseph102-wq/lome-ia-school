<?php

namespace App\Enums;

/**
 * Statut d'un rendez-vous de consultation gratuite.
 */
enum ConsultationStatus: string
{
    case Booked = 'booked';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Confirmé',
            self::Cancelled => 'Annulé',
        };
    }
}
