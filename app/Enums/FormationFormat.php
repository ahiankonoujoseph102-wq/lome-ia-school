<?php

namespace App\Enums;

/**
 * Toutes les formations sont enregistrées et se suivent à son rythme.
 * Le direct et le présentiel sont des sessions ponctuelles, annoncées au cas par cas (étape 7).
 */
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
            self::Recorded => 'Enregistrée · à suivre à votre rythme',
            self::Live => 'Session ponctuelle en direct (Google Meet)',
            self::Onsite => 'Session ponctuelle en présentiel à Lomé',
        };
    }

    /**
     * Libellé court pour les badges et les filtres.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Recorded => 'Enregistrée',
            self::Live => 'Session en direct',
            self::Onsite => 'Session à Lomé',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Recorded => 'Des vidéos claires, accessibles dès le paiement confirmé, à suivre à votre rythme depuis votre téléphone ou votre ordinateur, autant de fois que nécessaire.',
            self::Live => 'De temps en temps, une formation est reprise en direct sur Google Meet. Chaque session est annoncée sur le site.',
            self::Onsite => 'De temps en temps, une formation est organisée en présentiel à Lomé. Chaque session est annoncée sur le site.',
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
