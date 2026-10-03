<?php

namespace App\Mail;

use App\Models\FreeRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation d'une inscription gratuite : option choisie, date, horaires, adresse du présentiel
 * ou, en ligne, rappel que le lien Google Meet sera publié dans le groupe des inscrits.
 */
class FreeRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public FreeRegistration $registration, public string $firstName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Inscription confirmée : '.$this->registration->formation->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.free-registration');
    }
}
