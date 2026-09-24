<?php

namespace App\Mail;

use App\Models\Affiliate;
use App\Models\AffiliateWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mails de l'admin : nouvelle demande de retrait, coordonnées de retrait modifiées par un affilié.
 */
class AffiliateAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $previous  Anciennes coordonnées de retrait (modification).
     */
    public function __construct(
        public Affiliate $affiliate,
        public string $kind,
        public ?AffiliateWithdrawal $withdrawal = null,
        public array $previous = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'withdrawal_requested' => 'Nouvelle demande de retrait à payer',
            'payout_changed' => 'Coordonnées de retrait modifiées par un affilié',
            default => 'Programme d\'affiliation',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.affiliates.admin');
    }
}
