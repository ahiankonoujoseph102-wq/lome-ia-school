<?php

namespace App\LiveSessions;

use App\Models\LiveSession;

/**
 * Fichier .ics « Ajouter à mon agenda » (Google Agenda, agenda de l'iPhone, Outlook).
 * Heures en UTC (Lomé est à GMT) : chaque téléphone les affiche à son heure locale.
 */
class CalendarFile
{
    public static function for(LiveSession $session): string
    {
        $formation = $session->formation;
        $description = $formation->title.' · '.$session->format->longLabel();

        if ($session->isOnline() && $session->meet_url) {
            $description .= "\nLien Google Meet : ".$session->meet_url;
        }

        if (filled($session->description)) {
            $description .= "\n\n".$session->description;
        }

        $description .= "\n\n".route('student.sessions.show', $session);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Lomé IA School//Sessions//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:session-'.$session->id.'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'lomeiaschool'),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'SEQUENCE:'.($session->updated_at?->getTimestamp() ?? 0),
            'DTSTART:'.$session->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$session->ends_at->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.self::escape($session->displayTitle().' · '.$formation->title),
            'DESCRIPTION:'.self::escape($description),
            'LOCATION:'.self::escape($session->isOnline() ? (string) ($session->meet_url ?? 'Google Meet') : (string) $session->location),
            'STATUS:'.($session->isCancelled() ? 'CANCELLED' : 'CONFIRMED'),
        ];

        if ($session->isOnline() && $session->meet_url) {
            $lines[] = 'URL:'.$session->meet_url;
        }

        if (! $session->isCancelled()) {
            array_push($lines, 'BEGIN:VALARM', 'TRIGGER:-PT1H', 'ACTION:DISPLAY', 'DESCRIPTION:'.self::escape($session->displayTitle()), 'END:VALARM');
        }

        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    public static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $text);
    }

    /**
     * Lignes de 75 octets au plus, les suivantes commencent par une espace (norme iCalendar).
     */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $current = '';

        foreach (mb_str_split($line) as $char) {
            if (strlen($current.$char) > ($parts === [] ? 75 : 74)) {
                $parts[] = $current;
                $current = '';
            }

            $current .= $char;
        }

        $parts[] = $current;

        return implode("\r\n ", $parts);
    }
}
