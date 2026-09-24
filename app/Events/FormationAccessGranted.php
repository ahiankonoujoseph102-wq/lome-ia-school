<?php

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un élève vient d'obtenir l'accès à une formation (provisoire ou définitif).
 * Étape 6 : l'ajouter au groupe de discussion de la formation.
 */
class FormationAccessGranted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Enrollment $enrollment) {}
}
