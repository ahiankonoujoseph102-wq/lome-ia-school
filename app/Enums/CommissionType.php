<?php

namespace App\Enums;

/**
 * Ligne du compte d'un affilié.
 */
enum CommissionType: string
{
    /** Commission sur une vente validée. */
    case Sale = 'sale';

    /** Déduction : achat remboursé alors que sa commission avait déjà été versée (montant négatif). */
    case Reversal = 'reversal';
}
