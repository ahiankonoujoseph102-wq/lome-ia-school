<?php

namespace App\Enums;

enum EnrollmentSource: string
{
    case Purchase = 'purchase';
    case Admin = 'admin';
    case Free = 'free';
    case Pack = 'pack';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Achat',
            self::Admin => 'Accès donné par l\'équipe',
            self::Free => 'Inscription gratuite',
            self::Pack => 'Incluse dans une offre d\'accompagnement',
        };
    }
}
