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
 * E-mails au visiteur : confirmation, rappel la veille, rappel 1 h avant, déplacement, annulation,
 * recommandation de formation après la consultation.
 */
class ConsultationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Consultation $consultation, public string $kind) {}

    public function envelope(): Envelope
    {
        $when = ConsultationTime::short($this->consultation);

        return new Envelope(subject: match ($this->kind) {
            'confirmed' => 'Consultation confirmée : '.$when,
            'reminder_day' => 'Rappel : votre consultation '.($this->consultation->starts_at->isToday() ? 'aujourd\'hui' : 'demain').', '.$when,
            'reminder_hour' => 'Votre consultation commence dans 1 heure',
            'moved' => 'Consultation déplacée : '.$when,
            'cancelled' => 'Consultation annulée',
            'recommendation' => 'La formation que nous vous recommandons',
            default => 'Votre consultation gratuite',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.consultations.notice');
    }
}
