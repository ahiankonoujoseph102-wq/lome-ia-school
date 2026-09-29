<?php

namespace App\Mail;

use App\Accounts\EmailConfirmation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Code à 6 chiffres et bouton de confirmation de l'adresse e-mail (valables 30 minutes, une seule fois).
 * Envoyé juste après la réponse (defer) par App\Accounts\EmailConfirmation, jamais mis en file d'attente
 * (le code ne doit pas être enregistré en clair dans la table « jobs »).
 */
class EmailConfirmationMail extends Mailable
{
    use Queueable;

    public function __construct(public User $user, public string $code, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->code.' est votre code de confirmation Lomé IA School',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-confirmation',
            text: 'emails.email-confirmation-text',
            with: [
                'url' => route('email-confirmation.link', $this->token),
                'minutes' => EmailConfirmation::LIFETIME_MINUTES,
            ],
        );
    }
}
