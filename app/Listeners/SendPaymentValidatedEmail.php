<?php

namespace App\Listeners;

use App\Events\PaymentValidated;
use App\Mail\PaymentValidatedMail;
use Illuminate\Support\Facades\Mail;

class SendPaymentValidatedEmail
{
    public function handle(PaymentValidated $event): void
    {
        if ($event->purchase->user) {
            Mail::to($event->purchase->user)->send(new PaymentValidatedMail($event->purchase));
        }
    }
}
