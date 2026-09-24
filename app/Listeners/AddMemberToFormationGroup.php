<?php

namespace App\Listeners;

use App\Chat\GroupMembership;
use App\Events\FormationAccessGranted;

/**
 * Accès ouvert (provisoire ou validé) : l'élève rejoint aussitôt le groupe de la formation.
 */
class AddMemberToFormationGroup
{
    public function __construct(private GroupMembership $membership) {}

    public function handle(FormationAccessGranted $event): void
    {
        $this->membership->add($event->enrollment->user, $event->enrollment->formation);
    }
}
