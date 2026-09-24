<?php

namespace App\Listeners;

use App\Chat\GroupMembership;
use App\Events\AccountBlocked;

class RemoveBlockedUserFromGroups
{
    public function __construct(private GroupMembership $membership) {}

    public function handle(AccountBlocked $event): void
    {
        $this->membership->removeEverywhere($event->user);
    }
}
