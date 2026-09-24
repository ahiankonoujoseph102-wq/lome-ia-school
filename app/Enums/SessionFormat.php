<?php

namespace App\Enums;

enum SessionFormat: string
{
    case Online = 'online';
    case Onsite = 'onsite';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'En direct',
            self::Onsite => 'Présentiel',
        };
    }

    /**
     * Libellé complet : « En direct sur Google Meet », « En présentiel à Lomé ».
     */
    public function longLabel(): string
    {
        return match ($this) {
            self::Online => 'En direct sur Google Meet',
            self::Onsite => 'En présentiel à Lomé',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Online => 'video-camera',
            self::Onsite => 'map-pin',
        };
    }
}
