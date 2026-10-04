<?php

namespace App\Listeners;

use App\Mail\MailQuota;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

/**
 * Compte chaque e-mail réellement parti (un par destinataire, comme le quota du fournisseur).
 * Une erreur du compteur n'empêche jamais un e-mail de partir.
 */
class CountSentEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $recipients = count($message->getTo()) + count($message->getCc()) + count($message->getBcc());

            MailQuota::record(max(1, $recipients));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
