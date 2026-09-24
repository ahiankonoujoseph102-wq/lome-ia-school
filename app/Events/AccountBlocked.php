<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Compte bloqué par l'admin : il quitte immédiatement tous ses groupes de discussion.
 */
class AccountBlocked
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}
}
