<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Récapitulatif quotidien des messages non lus (au plus un par jour, seulement s'il y a des non-lus).
 */
class ChatDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{title: string, url: string, count: int, previews: list<string>}>  $groups
     */
    public function __construct(public string $firstName, public array $groups) {}

    public function envelope(): Envelope
    {
        $total = array_sum(array_column($this->groups, 'count'));

        return new Envelope(
            subject: $total > 1 ? $total.' nouveaux messages dans vos groupes' : '1 nouveau message dans votre groupe',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.chat.digest');
    }
}
