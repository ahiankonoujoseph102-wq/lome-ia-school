<?php

namespace App\Enums;

/**
 * Pourquoi un membre ne fait plus partie d'un groupe.
 */
enum ChatRemovalReason: string
{
    // Accès à la formation retiré (paiement refusé, remboursement, accès retiré par l'équipe).
    case AccessRevoked = 'access_revoked';

    // Compte bloqué par l'admin.
    case Blocked = 'blocked';

    // Exclu du groupe par un modérateur : un nouvel accès ne le réintègre pas.
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::AccessRevoked => 'Accès à la formation retiré',
            self::Blocked => 'Compte bloqué',
            self::Excluded => 'Exclu par l\'équipe',
        };
    }
}
