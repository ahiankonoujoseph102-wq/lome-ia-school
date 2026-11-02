<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Nouvelle demande de devis (page « Entreprises et réalisations sur mesure »), envoyée tout de suite à l'admin
 * (jamais par la file d'attente). L'adresse de la personne est en « Répondre à ».
 */
class QuoteRequestMail extends Mailable
{
    public function __construct(public QuoteRequest $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Demande de devis : '.$this->quote->serviceLabel().' ('.$this->quote->name.')',
            replyTo: [new Address($this->quote->email, $this->quote->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quote-request', text: 'emails.quote-request-text');
    }
}
