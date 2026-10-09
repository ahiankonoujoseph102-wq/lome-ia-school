<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vote d'un membre pour un choix d'un sondage (une ligne par choix coché).
 *
 * @property int $id
 * @property int $chat_poll_id
 * @property int $chat_poll_option_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChatPollVote extends Model
{
    protected $fillable = ['chat_poll_id', 'chat_poll_option_id', 'user_id'];

    /**
     * @return BelongsTo<ChatPoll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class, 'chat_poll_id');
    }
}
