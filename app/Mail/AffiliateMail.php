<?php

namespace App\Mail;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mails de l'affilié : nouvelle commission, commissions disponibles, retrait payé, retrait refusé.
 * Jamais d'information sur l'acheteur (ni nom, ni numéro, ni e-mail).
 */
class AffiliateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Affiliate $affiliate,
        public string $kind,
        public ?AffiliateCommission $commission = null,
        public ?AffiliateWithdrawal $withdrawal = null,
        public int $amount = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'commission_created' => 'Nouvelle commission : '.Money::fcfa((int) $this->commission?->amount),
            'commission_available' => 'Vos commissions sont disponibles',
            'withdrawal_paid' => 'Votre retrait a été payé',
            'withdrawal_rejected' => 'Votre demande de retrait a été refusée',
            default => 'Programme d\'affiliation',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.affiliates.notice');
    }
}
