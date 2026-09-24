<?php

namespace App\Events;

use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * L'accès d'un élève à une formation vient d'être retiré (paiement refusé ou remboursé).
 * Étape 6 : le retirer du groupe de discussion de la formation.
 */
class FormationAccessRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user, public Formation $formation, public ?Purchase $purchase = null) {}
}
