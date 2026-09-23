<?php

namespace App\Policies;

use App\Models\Formation;
use App\Models\User;

class FormationPolicy
{
    /**
     * Règle d'accès au contenu d'une formation (leçons, groupe de discussion) :
     * réservé aux élèves inscrits, et aux administrateurs.
     */
    public function access(User $user, Formation $formation): bool
    {
        return $user->isAdmin() || $user->isEnrolledIn($formation);
    }
}
