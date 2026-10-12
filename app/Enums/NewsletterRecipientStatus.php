<?php

namespace App\Enums;

/**
 * État de l'e-mail d'un destinataire d'un envoi groupé.
 *
 * pending → queued (travail mis en file d'attente) → sending (envoi commencé) → sent.
 * Un échec repasse en « pending » avec une date de nouvel essai, puis « failed » après le dernier essai.
 * « sending » n'est jamais renvoyé : un e-mail ne part jamais deux fois à la même personne.
 */
enum NewsletterRecipientStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    /**
     * États où l'e-mail peut encore partir.
     *
     * @return list<string>
     */
    public static function open(): array
    {
        return [self::Pending->value, self::Queued->value, self::Sending->value];
    }

    /**
     * États où l'e-mail est déjà dans la file d'attente ou en train de partir (comptés dans le quota du jour).
     *
     * @return list<string>
     */
    public static function inFlight(): array
    {
        return [self::Queued->value, self::Sending->value];
    }
}
