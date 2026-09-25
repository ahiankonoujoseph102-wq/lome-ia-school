<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

/**
 * Un rendez-vous de consultation se consulte avec son lien sécurisé, par l'élève dont le compte
 * lui est rattaché, ou par l'admin.
 */
class ConsultationPolicy
{
    public function view(User $user, Consultation $consultation): bool
    {
        return $user->isAdmin() || ($consultation->user_id !== null && $consultation->user_id === $user->id);
    }
}
