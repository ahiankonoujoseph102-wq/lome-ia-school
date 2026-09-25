<?php

namespace App\LiveSessions;

use App\Models\LiveSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Dates et heures des sessions, en français, à l'heure de Lomé (GMT), avec l'heure du Bénin (GMT+1).
 */
class SessionTime
{
    /**
     * « samedi 12 octobre 2026 ».
     */
    public static function day(LiveSession $session): string
    {
        return $session->starts_at->translatedFormat('l j F Y');
    }

    /**
     * « Samedi 12 octobre 2026 ».
     */
    public static function dayTitle(LiveSession $session): string
    {
        return Str::ucfirst(self::day($session));
    }

    /**
     * « 10 h 00 ».
     */
    public static function hour(CarbonInterface $time): string
    {
        // Espaces insécables : « 10 h 00 » ne se coupe jamais en fin de ligne.
        return str_replace(' ', "\u{00A0}", $time->format('G \h i'));
    }

    /**
     * « de 10 h 00 à 12 h 00, heure de Lomé (GMT) ».
     */
    public static function range(LiveSession $session): string
    {
        return 'de '.self::hour($session->starts_at).' à '.self::hour($session->ends_at).', heure de Lomé (GMT)';
    }

    /**
     * Heures dans les pays voisins qui ne sont pas à l'heure de Lomé : « 11 h 00 au Bénin (GMT+1) ».
     * Le Burkina Faso est à la même heure que Lomé (GMT).
     */
    public static function neighbours(LiveSession $session): string
    {
        return self::neighboursAt($session->starts_at);
    }

    /**
     * Même chose pour n'importe quelle heure de début (consultations, créneaux).
     */
    public static function neighboursAt(CarbonInterface $start): string
    {
        return collect((array) config('live_sessions.other_timezones'))
            ->map(function (string $timezone, string $country) use ($start): string {
                $local = $start->copy()->setTimezone($timezone);
                $offset = $local->offsetHours;

                return self::hour($local).' au '.$country.' (GMT'.($offset >= 0 ? '+' : '').$offset.')';
            })
            ->implode(' · ');
    }

    /**
     * Une ligne complète : « Samedi 12 octobre 2026, de 10 h 00 à 12 h 00, heure de Lomé (GMT) · 11 h 00 au Bénin (GMT+1) ».
     */
    public static function full(LiveSession $session): string
    {
        $neighbours = self::neighbours($session);

        return self::dayTitle($session).', '.self::range($session).($neighbours !== '' ? ' · '.$neighbours : '');
    }

    /**
     * « samedi 12 octobre à 10 h 00 » (sans l'année).
     */
    public static function short(LiveSession $session): string
    {
        return $session->starts_at->translatedFormat('l j F').' à '.self::hour($session->starts_at);
    }
}
