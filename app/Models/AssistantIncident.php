<?php

namespace App\Models;

use App\Enums\AssistantIncidentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incident de l'assistante (limite atteinte, erreur ou silence de Gemini), pour l'admin.
 * Le détail ne contient jamais la clé d'API. Effacé après 90 jours, comme les conversations.
 *
 * @property int $id
 * @property int|null $assistant_conversation_id
 * @property AssistantIncidentType $type
 * @property string|null $detail
 * @property CarbonImmutable|null $created_at
 */
class AssistantIncident extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $fillable = ['assistant_conversation_id', 'type', 'detail'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => AssistantIncidentType::class];
    }

    /**
     * @return BelongsTo<AssistantConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(AssistantConversation::RETENTION_DAYS));
    }
}
