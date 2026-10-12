<?php

namespace App\Enums;

/**
 * État d'un envoi d'e-mails groupé (« Envoi d'e-mails » de l'admin).
 */
enum NewsletterStatus: string
{
    case Draft = 'draft';
    case Sending = 'sending';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sending => 'En cours d\'envoi',
            self::Paused => 'En pause',
            self::Cancelled => 'Annulé',
            self::Completed => 'Terminé',
        };
    }
}
