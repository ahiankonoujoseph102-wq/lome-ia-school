<?php

namespace App\Packs;

use App\Enums\EnrollmentSource;
use App\Events\FormationAccessGranted;
use App\Events\FormationAccessRevoked;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\User;

/**
 * Accès donnés par une offre d'accompagnement (« pack »).
 *
 * - Accès à l'offre ouvert (provisoire, validé, manuel ou donné par l'équipe) : même accès à chaque formation
 *   incluse (et au Groupe VIP), sans doublon. Une formation que l'élève possède déjà n'est pas touchée.
 * - Accès à l'offre retiré (refus, remboursement, retrait) : seuls les accès ouverts par cette offre sont
 *   retirés. Jamais une formation achetée séparément, une inscription gratuite ni un accès donné par l'équipe.
 *   Si une autre offre de l'élève inclut aussi la formation, l'accès reste et passe à cette offre.
 *
 * Branché sur FormationAccessGranted / FormationAccessRevoked : les groupes de discussion, les sessions et
 * les autres suites habituelles s'appliquent donc à chaque formation incluse comme pour un achat.
 */
class PackAccess
{
    /**
     * L'élève vient d'obtenir l'accès à une offre : il obtient l'accès à toutes les formations incluses.
     *
     * @return list<Enrollment> Accès nouvellement ouverts.
     */
    public function grant(Enrollment $packEnrollment): array
    {
        $pack = $packEnrollment->formation;
        $user = $packEnrollment->user;

        if (! $pack->isPack()) {
            return [];
        }

        $granted = [];

        foreach ($pack->includedFormations()->get() as $formation) {
            // Une offre n'en contient jamais une autre (pas d'imbrication).
            if ($formation->isPack()) {
                continue;
            }

            $enrollment = $user->enrollments()->firstOrCreate(
                ['formation_id' => $formation->id],
                ['source' => EnrollmentSource::Pack, 'granted_at' => now(), 'pack_id' => $pack->id],
            );

            if ($enrollment->wasRecentlyCreated) {
                $granted[] = $enrollment;
                FormationAccessGranted::dispatch($enrollment);
            }
        }

        return $granted;
    }

    /**
     * L'accès à une offre vient d'être retiré : les accès qu'elle avait ouverts sont retirés aussi.
     *
     * @return list<Formation> Formations dont l'accès a été retiré.
     */
    public function revoke(User $user, Formation $pack): array
    {
        if (! $pack->isPack()) {
            return [];
        }

        $revoked = [];

        $enrollments = $user->enrollments()
            ->where('pack_id', $pack->id)
            ->where('source', EnrollmentSource::Pack->value)
            ->with('formation')
            ->get();

        foreach ($enrollments as $enrollment) {
            $otherPack = $this->otherPackIncluding($user, $enrollment->formation, $pack);

            if ($otherPack !== null) {
                $enrollment->forceFill(['pack_id' => $otherPack->id])->save();

                continue;
            }

            $enrollment->delete();
            $revoked[] = $enrollment->formation;
            FormationAccessRevoked::dispatch($user, $enrollment->formation);
        }

        return $revoked;
    }

    /**
     * Autre offre à laquelle l'élève a encore accès et qui inclut cette formation.
     */
    private function otherPackIncluding(User $user, Formation $formation, Formation $except): ?Formation
    {
        return $formation->packs()
            ->whereKeyNot($except->id)
            ->whereIn('formations.id', $user->enrollments()->select('formation_id'))
            ->first();
    }
}
