<?php

namespace App\Http\Controllers\Chat\Concerns;

use App\Chat\ChatViewer;
use App\Chat\GroupMembership;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\Formation;
use Illuminate\Http\Request;

trait ResolvesChatMember
{
    /**
     * Membre actif du groupe de la formation (élève ayant accès, admin ou modérateur), sinon accès refusé.
     */
    protected function viewer(Request $request, Formation $formation): ChatViewer
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $group = ChatGroup::forFormation($formation);
        $group->setRelation('formation', $formation);
        $member = app(GroupMembership::class)->membershipFor($user, $group);

        abort_if($member === null, 403, 'Ce groupe est réservé aux élèves de la formation.');

        $member->setRelation('group', $group);

        return new ChatViewer($user, $member, $group);
    }

    /**
     * Message appartenant bien à ce groupe.
     */
    protected function messageOf(ChatViewer $viewer, ChatMessage $message): ChatMessage
    {
        abort_unless($message->chat_group_id === $viewer->group->id, 404);
        $message->setRelation('group', $viewer->group);

        return $message;
    }
}
