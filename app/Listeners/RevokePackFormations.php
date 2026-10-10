<?php

namespace App\Listeners;

use App\Events\FormationAccessRevoked;
use App\Packs\PackAccess;

/**
 * Accès à une offre d'accompagnement retiré : les accès ouverts par cette offre sont retirés aussi
 * (jamais une formation achetée séparément).
 */
class RevokePackFormations
{
    public function __construct(private PackAccess $packs) {}

    public function handle(FormationAccessRevoked $event): void
    {
        $this->packs->revoke($event->user, $event->formation);
    }
}
