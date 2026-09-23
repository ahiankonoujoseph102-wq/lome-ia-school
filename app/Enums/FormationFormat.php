<?php

namespace App\Enums;

enum FormationFormat: string
{
    case Recorded = 'recorded';
    case Live = 'live';
    case Onsite = 'onsite';

    /**
     * Libellé complet affiché sur les pages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Enregistrée (en ligne)',
            self::Live => 'En direct (Google Meet)',
            self::Onsite => 'Présentiel à Lomé',
        };
    }

    /**
     * Libellé court pour les badges et les filtres.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Recorded => 'En ligne',
            self::Live => 'En direct',
            self::Onsite => 'Présentiel',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Recorded => 'Des vidéos claires à suivre à votre rythme, depuis votre téléphone ou votre ordinateur, autant de fois que nécessaire.',
            self::Live => 'Des sessions en direct sur Google Meet pour apprendre avec le formateur, poser vos questions et pratiquer en temps réel.',
            self::Onsite => 'Des séances en salle à Adidogomé, Lomé, pour apprendre en groupe avec un accompagnement direct du formateur.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Recorded => 'play-circle',
            self::Live => 'video-camera',
            self::Onsite => 'map-pin',
        };
    }
}
