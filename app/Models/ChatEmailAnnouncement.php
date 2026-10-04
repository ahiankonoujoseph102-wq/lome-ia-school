<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Annonce d'un groupe envoyée aussi par e-mail aux membres (App\Chat\EmailAnnouncements) : nombre
 * d'e-mails prévus, partis, en échec et abandonnés (message supprimé avant l'envoi).
 *
 * @property int $id
 * @property int $chat_group_id
 * @property int|null $chat_message_id
 * @property int|null $user_id
 * @property int $recipients
 * @property int $sent
 * @property int $failed
 * @property int $skipped
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatGroup $group
 * @property-read ChatMessage|null $message
 * @property-read User|null $author
 */
class ChatEmailAnnouncement extends Model
{
    protected $fillable = ['chat_group_id', 'chat_message_id', 'user_id', 'recipients', 'sent', 'failed', 'skipped'];

    protected function casts(): array
    {
        return [
            'recipients' => 'integer',
            'sent' => 'integer',
            'failed' => 'integer',
            'skipped' => 'integer',
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
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * E-mails encore dans la file d'attente.
     */
    public function pending(): int
    {
        return max(0, $this->recipients - $this->sent - $this->failed - $this->skipped);
    }

    public function isFinished(): bool
    {
        return $this->pending() === 0;
    }
}
