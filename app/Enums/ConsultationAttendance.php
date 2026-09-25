<?php

namespace App\Enums;

/**
 * Présence du visiteur à la consultation (indiquée par l'admin après l'appel).
 */
enum ConsultationAttendance: string
{
    case Present = 'present';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
        };
    }
}
