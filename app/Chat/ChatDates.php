<?php

namespace App\Chat;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Dates des groupes, en français et à l'heure de Lomé.
 */
class ChatDates
{
    /**
     * Séparateur de jour : « Aujourd'hui », « Hier », « Lundi », « 12 septembre 2026 ».
     */
    public static function day(CarbonInterface $date): string
    {
        return match (true) {
            $date->isToday() => 'Aujourd\'hui',
            $date->isYesterday() => 'Hier',
            $date->greaterThan(now()->subDays(6)->startOfDay()) => Str::ucfirst($date->translatedFormat('l')),
            $date->isCurrentYear() => $date->translatedFormat('j F'),
            default => $date->translatedFormat('j F Y'),
        };
    }

    /**
     * Heure ou date courte de la liste des groupes : « 14:05 », « Hier », « 12/09/26 ».
     */
    public static function short(CarbonInterface $date): string
    {
        return match (true) {
            $date->isToday() => $date->format('H:i'),
            $date->isYesterday() => 'Hier',
            $date->greaterThan(now()->subDays(6)->startOfDay()) => Str::ucfirst($date->translatedFormat('l')),
            default => $date->format('d/m/y'),
        };
    }
}
