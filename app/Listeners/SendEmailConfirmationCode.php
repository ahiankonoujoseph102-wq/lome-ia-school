<?php

namespace App\Listeners;

use App\Accounts\EmailConfirmation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Juste après l'inscription : e-mail avec le code à 6 chiffres et le bouton de confirmation.
 */
class SendEmailConfirmationCode
{
    public function __construct(private EmailConfirmation $confirmation) {}

    public function handle(Registered $event): void
    {
        if ($event->user instanceof User && ! $event->user->hasConfirmedEmail()) {
            $this->confirmation->send($event->user);
        }
    }
}
