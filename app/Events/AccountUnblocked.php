<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Compte débloqué : il retrouve les groupes des formations auxquelles il a encore accès.
 */
class AccountUnblocked
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}
}
