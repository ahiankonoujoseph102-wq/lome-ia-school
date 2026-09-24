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
 * Paiement refusé, avec le motif.
 */
class PaymentRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Purchase $purchase) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Paiement refusé : '.$this->purchase->item_label,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payments.rejected');
    }
}
