<?php

namespace App\Policies;

use App\Models\LiveSession;
use App\Models\User;

/**
 * Sessions : visibles (avec le lien Google Meet) par les élèves ayant accès à la formation,
 * accès provisoire compris, et par l'équipe. Jamais par les autres visiteurs.
 */
class LiveSessionPolicy
{
    public function view(User $user, LiveSession $session): bool
    {
        if ($user->isBlocked()) {
            return false;
        }

        return $user->isStaff() || $user->enrollments()->where('formation_id', $session->formation_id)->exists();
    }

    public function register(User $user, LiveSession $session): bool
    {
        return $this->view($user, $session) && $session->isOnsite() && ! $user->isStaff();
    }
}
