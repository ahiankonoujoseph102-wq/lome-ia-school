<?php

namespace App\Chat;

use App\Models\ChatGroup;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Chargement des messages d'un groupe : dernière page, historique en remontant, nouveautés.
 */
class ConversationLoader
{
    /**
     * @return Builder<ChatMessage>
     */
    public function query(ChatGroup $group): Builder
    {
        return ChatMessage::query()
            ->where('chat_group_id', $group->id)
            ->with(['author', 'replyTo.author']);
    }

    /**
     * Derniers messages, du plus ancien au plus récent.
     *
     * @return Collection<int, ChatMessage>
     */
    public function latest(ChatGroup $group, int $count): Collection
    {
        return $this->query($group)->orderByDesc('id')->limit($count)->get()->reverse()->values();
    }

    /**
     * Page d'historique juste avant ce message, du plus ancien au plus récent.
     *
     * @return Collection<int, ChatMessage>
     */
    public function before(ChatGroup $group, int $beforeId): Collection
    {
        return $this->query($group)
            ->where('id', '<', $beforeId)
            ->orderByDesc('id')
            ->limit((int) config('chat.page_size'))
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Messages arrivés après ce message.
     *
     * @return Collection<int, ChatMessage>
     */
    public function after(ChatGroup $group, int $afterId): Collection
    {
        return $this->query($group)->where('id', '>', $afterId)->orderBy('id')->limit(200)->get();
    }

    public function previousOf(ChatGroup $group, int $id): ?ChatMessage
    {
        return ChatMessage::query()->where('chat_group_id', $group->id)->where('id', '<', $id)->orderByDesc('id')->first();
    }

    public function hasOlder(ChatGroup $group, ?int $firstId): bool
    {
        return $firstId !== null && ChatMessage::query()->where('chat_group_id', $group->id)->where('id', '<', $firstId)->exists();
    }
}
