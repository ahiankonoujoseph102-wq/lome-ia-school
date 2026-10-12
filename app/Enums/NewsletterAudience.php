<?php

namespace App\Enums;

/**
 * Destinataires d'un envoi groupé. Dans les deux cas : adresse e-mail confirmée, compte non bloqué et
 * personne non désabonnée des envois groupés.
 */
enum NewsletterAudience: string
{
    case All = 'all';
    case OptIn = 'optin';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tous les comptes à l\'adresse e-mail confirmée',
            self::OptIn => 'Seulement ceux qui ont coché « Je souhaite être informé(e) des nouvelles formations »',
        };
    }
}
