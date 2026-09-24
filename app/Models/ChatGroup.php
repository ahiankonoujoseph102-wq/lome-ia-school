<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Groupe de discussion d'une formation (un par formation, créé automatiquement).
 *
 * @property int $id
 * @property int $formation_id
 * @property bool $announcements_only Seuls l'admin et les modérateurs peuvent écrire.
 * @property string|null $rules Règles affichées à l'entrée du groupe.
 * @property Carbon|null $rules_updated_at
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Formation $formation
 * @property-read Collection<int, ChatGroupMember> $members
 * @property-read Collection<int, ChatMessage> $messages
 */
class ChatGroup extends Model
{
    protected $fillable = ['formation_id', 'announcements_only', 'rules', 'rules_updated_at', 'last_message_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'announcements_only' => 'boolean',
            'rules_updated_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Groupe de la formation, créé s'il n'existe pas encore.
     */
    public static function forFormation(Formation $formation): self
    {
        return self::query()->firstOrCreate(
            ['formation_id' => $formation->id],
            ['rules' => config('chat.default_rules'), 'rules_updated_at' => now()],
        );
    }

    /**
     * @return BelongsTo<Formation, $this>
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * @return HasMany<ChatGroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class);
    }

    /**
     * Membres présents (ni retirés ni exclus).
     *
     * @return HasMany<ChatGroupMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('removed_at');
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Messages épinglés, le plus récent en premier.
     *
     * @return HasMany<ChatMessage, $this>
     */
    public function pinnedMessages(): HasMany
    {
        return $this->messages()->whereNotNull('pinned_at')->whereNull('deleted_at')->latest('pinned_at');
    }

    /**
     * Messages non lus par ce membre (hors ses propres messages et les messages supprimés).
     *
     * @return Builder<ChatMessage>
     */
    public function unreadMessagesFor(ChatGroupMember $member): Builder
    {
        return ChatMessage::query()
            ->where('chat_group_id', $this->id)
            ->where('id', '>', (int) $member->last_read_message_id)
            ->where('user_id', '!=', $member->user_id)
            ->whereNull('deleted_at');
    }

    public function latestMessageId(): int
    {
        return (int) $this->messages()->max('id');
    }
}
