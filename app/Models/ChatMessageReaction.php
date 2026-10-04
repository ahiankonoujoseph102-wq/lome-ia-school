<?php

namespace App\Models;

use App\Enums\ChatReaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Réaction d'un membre à un message (une seule par personne et par message).
 *
 * @property int $id
 * @property int $chat_message_id
 * @property int $user_id
 * @property ChatReaction $emoji
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChatMessageReaction extends Model
{
    protected $fillable = ['chat_message_id', 'user_id', 'emoji'];

    protected function casts(): array
    {
        return ['emoji' => ChatReaction::class];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
