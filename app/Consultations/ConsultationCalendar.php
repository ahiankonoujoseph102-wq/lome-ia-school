<?php

namespace App\Consultations;

use App\LiveSessions\CalendarFile;
use App\Models\Consultation;
use App\Support\GoogleCalendar;

/**
 * Fichier .ics « Ajouter à mon agenda » d'une consultation (heures en UTC, lien Google Meet inclus).
 */
class ConsultationCalendar
{
    public const string TITLE = 'Consultation gratuite · Lomé IA School';

    public static function for(Consultation $consultation, ?string $meetUrl): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Lomé IA School//Consultation//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:consultation-'.$consultation->id.'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'lomeiaschool'),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'SEQUENCE:'.($consultation->updated_at?->getTimestamp() ?? 0),
            'DTSTART:'.$consultation->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$consultation->ends_at->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.CalendarFile::escape(self::TITLE),
            'DESCRIPTION:'.CalendarFile::escape(self::description($consultation, $meetUrl)),
            'LOCATION:'.CalendarFile::escape($meetUrl ?? 'Google Meet'),
            'STATUS:'.($consultation->isCancelled() ? 'CANCELLED' : 'CONFIRMED'),
        ];

        if ($meetUrl) {
            $lines[] = 'URL:'.$meetUrl;
        }

        if (! $consultation->isCancelled()) {
            array_push($lines, 'BEGIN:VALARM', 'TRIGGER:-PT1H', 'ACTION:DISPLAY', 'DESCRIPTION:'.CalendarFile::escape('Consultation gratuite'), 'END:VALARM');
        }

        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", array_map(CalendarFile::fold(...), $lines))."\r\n";
    }

    /**
     * Lien « Ajouter à Google Agenda » avec le rendez-vous prérempli (lien Meet lu au moment de l'affichage).
     */
    public static function googleUrl(Consultation $consultation, ?string $meetUrl): string
    {
        return GoogleCalendar::url(self::TITLE, $consultation->starts_at, $consultation->ends_at, self::description($consultation, $meetUrl), $meetUrl ?? 'Google Meet');
    }

    private static function description(Consultation $consultation, ?string $meetUrl): string
    {
        $description = 'Consultation gratuite de Lomé IA School, en direct en ligne par appel vidéo.';

        if ($meetUrl) {
            $description .= "\nLien Google Meet : ".$meetUrl;
        }

        return $description."\n\nDéplacer ou annuler : ".$consultation->manageUrl();
    }
}
