<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Élève',
            self::Admin => 'Administrateur',
        };
    }
}
