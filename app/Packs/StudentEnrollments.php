<?php

namespace App\Packs;

use App\Enums\FormationKind;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Formations affichées dans l'espace élève (« Mes formations », accueil de Mon espace).
 *
 * Une offre d'accompagnement n'a pas de leçons et le Groupe VIP n'est qu'un groupe : seules les formations
 * habituelles sont listées (une formation incluse dans une offre indique de quelle offre elle vient).
 */
class StudentEnrollments
{
    /**
     * @return Collection<int, Enrollment>
     */
    public static function for(User $user): Collection
    {
        return $user->enrollments()
            ->with(['formation', 'purchase', 'pack'])
            ->whereHas('formation', fn ($query) => $query->where('kind', FormationKind::Course->value))
            ->latest('granted_at')
            ->get();
    }
}
