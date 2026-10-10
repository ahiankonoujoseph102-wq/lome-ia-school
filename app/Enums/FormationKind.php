<?php

namespace App\Enums;

/**
 * Type d'une formation.
 *
 * - Course : formation habituelle (leçons, groupe de discussion, page publique).
 * - Pack : offre d'accompagnement qui donne accès à plusieurs formations incluses (page /accompagnement).
 * - Group : groupe de discussion seul, sans page ni contenu (le Groupe VIP des offres) ; jamais vendu.
 */
enum FormationKind: string
{
    case Course = 'course';
    case Pack = 'pack';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'Formation',
            self::Pack => 'Offre d\'accompagnement',
            self::Group => 'Groupe de discussion',
        };
    }
}
