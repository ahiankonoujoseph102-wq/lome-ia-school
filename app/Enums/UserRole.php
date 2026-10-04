<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Élève',
            self::Moderator => 'Modérateur',
            self::Admin => 'Administrateur',
        };
    }

    /**
     * Admin et modérateurs : membres de tous les groupes, avec les outils de modération.
     */
    public function isStaff(): bool
    {
        return $this !== self::Student;
    }
}
