<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Annonce importante d'un groupe, envoyée par un administrateur (envoi déjà en file d'attente :
 * App\Jobs\SendChatAnnouncementEmail).
 */
class ChatAnnouncementMail extends Mailable
{
    public function __construct(
        public string $firstName,
        public string $groupTitle,
        public string $authorName,
        public string $body,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Annonce importante : '.$this->groupTitle);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.chat.announcement');
    }
}
