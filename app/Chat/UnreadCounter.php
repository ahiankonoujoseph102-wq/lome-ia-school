<?php

namespace App\Chat;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Messages non lus par groupe, en une seule requête (compatible SQLite et MySQL).
 */
class UnreadCounter
{
    /**
     * @return Collection<int, int> Nombre de non-lus, indexé par identifiant de groupe.
     */
    public function forUser(User $user): Collection
    {
        return DB::table('chat_group_members')
            ->join('chat_messages', 'chat_messages.chat_group_id', '=', 'chat_group_members.chat_group_id')
            ->where('chat_group_members.user_id', $user->id)
            ->whereNull('chat_group_members.removed_at')
            ->whereNull('chat_messages.deleted_at')
            ->where('chat_messages.user_id', '!=', $user->id)
            ->whereRaw('chat_messages.id > COALESCE(chat_group_members.last_read_message_id, 0)')
            ->groupBy('chat_group_members.chat_group_id')
            ->selectRaw('chat_group_members.chat_group_id as group_id, COUNT(*) as unread')
            ->pluck('unread', 'group_id')
            ->map(fn (mixed $count): int => (int) $count);
    }

    /**
     * Total pour la barre de navigation (calculé une seule fois par page).
     */
    public function totalFor(User $user): int
    {
        return once(fn (): int => (int) $this->forUser($user)->sum());
    }
}
