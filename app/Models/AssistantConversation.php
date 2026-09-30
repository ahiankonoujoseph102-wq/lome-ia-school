<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une conversation avec Ama (Gemini). Aucune donnée personnelle : le visiteur est désigné par une empreinte
 * HMAC d'un identifiant aléatoire de son navigateur, et les messages sont nettoyés avant l'enregistrement
 * (App\Assistant\PersonalData). Effacée automatiquement après RETENTION_DAYS jours (app:prune-assistant).
 *
 * @property int $id
 * @property string $public_id
 * @property string $visitor_hash
 * @property string|null $page
 * @property string|null $formation
 * @property int $messages_count
 * @property bool $voice_used
 * @property bool $fallback_shown
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, AssistantMessage> $messages
 */
class AssistantConversation extends Model
{
    use Prunable;

    public const int RETENTION_DAYS = 90;

    protected $fillable = ['public_id', 'visitor_hash', 'page', 'formation', 'messages_count', 'voice_used', 'fallback_shown', 'last_message_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'messages_count' => 'integer',
            'voice_used' => 'boolean',
            'fallback_shown' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AssistantMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class)->orderBy('id');
    }

    /**
     * Conversations à effacer : dernier message il y a plus de 90 jours.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $query) => $query
            ->where('last_message_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->orWhere(fn (Builder $query) => $query->whereNull('last_message_at')->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))));
    }
}
