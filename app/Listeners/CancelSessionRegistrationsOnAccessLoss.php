<?php

namespace App\Listeners;

use App\Events\AccountBlocked;
use App\Events\FormationAccessRevoked;
use App\LiveSessions\SessionRegistrar;

/**
 * Accès à la formation retiré ou compte bloqué : les inscriptions aux sessions à venir sont annulées
 * et les places libérées sont proposées à la liste d'attente.
 */
class CancelSessionRegistrationsOnAccessLoss
{
    public function __construct(private SessionRegistrar $registrar) {}

    public function handle(FormationAccessRevoked|AccountBlocked $event): void
    {
        if ($event instanceof FormationAccessRevoked) {
            $this->registrar->cancelForAccessLoss($event->user, $event->formation->id, 'access_revoked');

            return;
        }

        $this->registrar->cancelForAccessLoss($event->user, null, 'blocked');
    }
}
