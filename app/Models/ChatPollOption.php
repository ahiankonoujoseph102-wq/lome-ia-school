<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Choix d'un sondage.
 *
 * @property int $id
 * @property int $chat_poll_id
 * @property string $label
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChatPollOption extends Model
{
    protected $fillable = ['chat_poll_id', 'label', 'position'];

    /**
     * @return BelongsTo<ChatPoll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class, 'chat_poll_id');
    }
}
