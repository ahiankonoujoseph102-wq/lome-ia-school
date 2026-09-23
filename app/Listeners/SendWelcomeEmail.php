<?php

namespace App\Listeners;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            Mail::to($event->user)->send(new WelcomeMail($event->user));
        }
    }
}
