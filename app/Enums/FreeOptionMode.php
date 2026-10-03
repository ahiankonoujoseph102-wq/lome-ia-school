<?php

namespace App\Enums;

/**
 * Déroulement d'une option d'inscription gratuite : en présentiel (adresse affichée) ou en ligne (Google Meet,
 * lien publié dans le groupe des inscrits).
 */
enum FreeOptionMode: string
{
    case Onsite = 'onsite';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'Présentiel',
            self::Online => 'En ligne (Google Meet)',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Onsite => 'map-pin',
            self::Online => 'video-camera',
        };
    }
}
