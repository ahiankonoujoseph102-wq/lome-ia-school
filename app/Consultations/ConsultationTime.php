<?php

namespace App\Consultations;

use App\LiveSessions\SessionTime;
use App\Models\Consultation;
use Illuminate\Support\Str;

/**
 * Date et heure d'une consultation, en français, à l'heure de Lomé (GMT), avec l'heure du Bénin (GMT+1).
 */
class ConsultationTime
{
    /**
     * « Lundi 5 octobre 2026 ».
     */
    public static function day(Consultation $consultation): string
    {
        return Str::ucfirst($consultation->starts_at->translatedFormat('l j F Y'));
    }

    /**
     * « de 9 h 00 à 9 h 30, heure de Lomé (GMT) ».
     */
    public static function range(Consultation $consultation): string
    {
        return 'de '.SessionTime::hour($consultation->starts_at).' à '.SessionTime::hour($consultation->ends_at).', heure de Lomé (GMT)';
    }

    /**
     * « 10 h 00 au Bénin (GMT+1) ».
     */
    public static function neighbours(Consultation $consultation): string
    {
        return SessionTime::neighboursAt($consultation->starts_at);
    }

    /**
     * « Lundi 5 octobre 2026, de 9 h 00 à 9 h 30, heure de Lomé (GMT) · 10 h 00 au Bénin (GMT+1) ».
     */
    public static function full(Consultation $consultation): string
    {
        $neighbours = self::neighbours($consultation);

        return self::day($consultation).', '.self::range($consultation).($neighbours !== '' ? ' · '.$neighbours : '');
    }

    /**
     * « lundi 5 octobre à 9 h 00 » (sans l'année).
     */
    public static function short(Consultation $consultation): string
    {
        return $consultation->starts_at->translatedFormat('l j F').' à '.SessionTime::hour($consultation->starts_at);
    }
}
