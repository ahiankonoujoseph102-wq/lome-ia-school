<?php

namespace App\Models;

use App\Enums\ChatRemovalReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Appartenance d'un compte à un groupe, avec son état de lecture et ses réglages.
 *
 * @property int $id
 * @property int $chat_group_id
 * @property int $user_id
 * @property Carbon $joined_at
 * @property Carbon|null $removed_at
 * @property ChatRemovalReason|null $removal_reason
 * @property Carbon|null $muted_until Sourdine décidée par l'équipe : lecture seule jusqu'à cette date.
 * @property int|null $muted_by
 * @property int|null $last_read_message_id
 * @property int|null $last_digested_message_id Dernier message déjà annoncé dans un e-mail récapitulatif.
 * @property bool $notifications_muted Notifications de ce groupe coupées par le membre.
 * @property Carbon|null $rules_seen_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatGroup $group
 * @property-read User $user
 */
class ChatGroupMember extends Model
{
    protected $fillable = [
        'chat_group_id',
        'user_id',
        'joined_at',
        'removed_at',
        'removal_reason',
        'muted_until',
        'muted_by',
        'last_read_message_id',
        'last_digested_message_id',
        'notifications_muted',
        'rules_seen_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
            'removal_reason' => ChatRemovalReason::class,
            'muted_until' => 'datetime',
            'last_read_message_id' => 'integer',
            'last_digested_message_id' => 'integer',
            'notifications_muted' => 'boolean',
            'rules_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->removed_at === null;
    }

    public function isExcluded(): bool
    {
        return $this->removal_reason === ChatRemovalReason::Excluded;
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    /**
     * Les règles ont changé depuis la dernière lecture (ou n'ont jamais été lues).
     */
    public function mustSeeRules(): bool
    {
        $rulesUpdatedAt = $this->group->rules_updated_at;

        return filled($this->group->rules)
            && ($this->rules_seen_at === null || ($rulesUpdatedAt !== null && $rulesUpdatedAt->greaterThan($this->rules_seen_at)));
    }
}
