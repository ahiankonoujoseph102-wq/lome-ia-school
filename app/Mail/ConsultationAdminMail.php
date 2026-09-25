<?php

namespace App\Mail;

use App\Consultations\ConsultationTime;
use App\Models\Consultation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mails à l'admin : nouvelle réservation, déplacement, annulation par le visiteur.
 */
class ConsultationAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Consultation $consultation, public string $kind) {}

    public function envelope(): Envelope
    {
        $when = ConsultationTime::short($this->consultation);

        return new Envelope(subject: match ($this->kind) {
            'booked' => 'Nouvelle consultation : '.$this->consultation->name.', '.$when,
            'moved' => 'Consultation déplacée : '.$this->consultation->name.', '.$when,
            'cancelled' => 'Consultation annulée : '.$this->consultation->name.', '.$when,
            default => 'Consultation gratuite',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.consultations.admin');
    }
}
