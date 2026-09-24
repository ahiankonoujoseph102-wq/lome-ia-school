<?php

namespace App\Listeners;

use App\Chat\GroupMembership;
use App\Events\AccountUnblocked;

class RestoreUnblockedUserGroups
{
    public function __construct(private GroupMembership $membership) {}

    public function handle(AccountUnblocked $event): void
    {
        $this->membership->restoreAfterUnblock($event->user);
    }
}
