<?php

namespace App\Listeners;

use App\Events\PaymentRejected;
use App\Mail\PaymentRejectedMail;
use Illuminate\Support\Facades\Mail;

class SendPaymentRejectedEmail
{
    public function handle(PaymentRejected $event): void
    {
        if ($event->purchase->user) {
            Mail::to($event->purchase->user)->send(new PaymentRejectedMail($event->purchase));
        }
    }
}
