<?php

namespace App\Listeners;

use App\Events\FormationAccessGranted;
use App\Packs\PackAccess;

/**
 * Accès ouvert à une offre d'accompagnement : même accès aux formations incluses et au Groupe VIP.
 */
class GrantPackFormations
{
    public function __construct(private PackAccess $packs) {}

    public function handle(FormationAccessGranted $event): void
    {
        $this->packs->grant($event->enrollment);
    }
}
