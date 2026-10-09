<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lieu partagé dans un groupe : lien Google Maps (vérifié par App\Support\GoogleMaps), nom et adresse facultatifs.
 *
 * @property int $id
 * @property int $chat_message_id
 * @property string|null $name
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $maps_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChatMessageLocation extends Model
{
    protected $fillable = ['chat_message_id', 'name', 'address', 'latitude', 'longitude', 'maps_url'];

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function title(): string
    {
        return filled($this->name) ? (string) $this->name : 'Lieu partagé';
    }
}
