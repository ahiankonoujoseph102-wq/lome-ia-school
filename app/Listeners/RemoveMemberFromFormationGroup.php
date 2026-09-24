<?php

namespace App\Listeners;

use App\Chat\GroupMembership;
use App\Events\FormationAccessRevoked;

/**
 * Accès retiré (paiement refusé, remboursement) : l'élève quitte immédiatement le groupe.
 */
class RemoveMemberFromFormationGroup
{
    public function __construct(private GroupMembership $membership) {}

    public function handle(FormationAccessRevoked $event): void
    {
        $this->membership->remove($event->user, $event->formation);
    }
}
