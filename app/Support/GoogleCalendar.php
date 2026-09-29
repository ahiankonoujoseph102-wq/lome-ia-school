<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Lien « Ajouter à Google Agenda » : ouvre Google Agenda (application ou site) avec le rendez-vous
 * prérempli (titre, date, heure, durée, description, lieu). Plus simple qu'un fichier .ics sur téléphone.
 *
 * Les heures sont écrites à l'heure de Lomé, avec le fuseau Africa/Lome (ctz) : Google Agenda les
 * convertit ensuite à l'heure du téléphone (Bénin, voyage…).
 */
class GoogleCalendar
{
    public const string TIMEZONE = 'Africa/Lome';

    public static function url(string $title, CarbonInterface $start, CarbonInterface $end, string $details = '', string $location = ''): string
    {
        $format = fn (CarbonInterface $date): string => $date->copy()->setTimezone(self::TIMEZONE)->format('Ymd\THis');

        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter([
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $format($start).'/'.$format($end),
            'ctz' => self::TIMEZONE,
            'details' => $details,
            'location' => $location,
        ], fn (string $value): bool => $value !== ''), '', '&', PHP_QUERY_RFC3986);
    }
}
