<?php

namespace App\Mail;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte envoyée à l'admin pour chaque nouveau paiement à vérifier.
 */
class NewPaymentToVerifyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Purchase $purchase) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Paiement à vérifier : '.$this->purchase->formatted_amount.' · '.$this->purchase->formation->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payments.admin-new');
    }
}
