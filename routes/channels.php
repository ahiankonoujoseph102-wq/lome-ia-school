<?php

use App\Chat\GroupMembership;
use App\Models\ChatGroup;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal privé d'un groupe de discussion : réservé à ses membres actifs, à l'admin et aux modérateurs.
Broadcast::channel('chat.group.{group}', function (User $user, ChatGroup $group): bool {
    return app(GroupMembership::class)->membershipFor($user, $group) !== null;
});
