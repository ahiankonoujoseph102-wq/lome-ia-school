<?php

namespace App\Listeners;

use App\Events\PaymentSubmitted;
use App\Mail\NewPaymentToVerifyMail;
use App\Mail\PaymentProofReceivedMail;
use Illuminate\Support\Facades\Mail;

/**
 * Preuve de paiement reçue : confirmation à l'élève et alerte à l'admin.
 */
class SendPaymentSubmittedEmails
{
    public function handle(PaymentSubmitted $event): void
    {
        $purchase = $event->purchase;

        if ($purchase->user) {
            Mail::to($purchase->user)->send(new PaymentProofReceivedMail($purchase));
        }

        $adminEmail = (string) config('site.payments.admin_email');

        if ($adminEmail !== '') {
            Mail::to($adminEmail)->send(new NewPaymentToVerifyMail($purchase));
        }
    }
}
