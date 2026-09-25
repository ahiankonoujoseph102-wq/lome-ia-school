<?php

namespace App\Mail;

use App\Models\AdminActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte de sécurité à l'admin : moyens de paiement, réglages d'affiliation ou rôles modifiés.
 */
class SecurityAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AdminActivity $activity, public string $byName, public string $ip) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Alerte de sécurité : '.mb_strtolower($this->activity->action->label()));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.security-alert');
    }
}
