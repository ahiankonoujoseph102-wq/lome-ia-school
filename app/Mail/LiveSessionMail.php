<?php

namespace App\Mail;

use App\LiveSessions\SessionTime;
use App\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mails des sessions : rappels (veille et 1 h avant), modification, annulation, place libérée,
 * réservation expirée. Le contenu dépend de $kind (voir App\LiveSessions\SessionNotifier).
 */
class LiveSessionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $changes  Ce qui a changé (modification).
     */
    public function __construct(
        public LiveSession $session,
        public string $kind,
        public string $firstName,
        public array $changes = [],
    ) {}

    public function envelope(): Envelope
    {
        $when = SessionTime::short($this->session);

        return new Envelope(subject: match ($this->kind) {
            'reminder_day' => 'Rappel : session demain, '.$when,
            'reminder_hour' => 'La session commence dans 1 heure',
            'changed' => 'Session modifiée : '.$this->session->displayTitle(),
            'cancelled' => 'Session annulée : '.$this->session->displayTitle(),
            'seat_available' => 'Une place s\'est libérée pour vous',
            'reservation_expired' => 'Votre place réservée a été libérée',
            default => $this->session->displayTitle(),
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sessions.notice');
    }
}
