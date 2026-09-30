<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un message d'une conversation avec Ama : « user » (visiteur, texte nettoyé) ou « model » (Ama).
 *
 * @property int $id
 * @property int $assistant_conversation_id
 * @property string $role
 * @property string $content
 * @property bool $unanswered Ama a dit qu'elle n'avait pas l'information.
 * @property CarbonImmutable|null $created_at
 * @property-read AssistantConversation $conversation
 */
class AssistantMessage extends Model
{
    public const UPDATED_AT = null;

    public const string VISITOR = 'user';

    public const string AMA = 'model';

    protected $fillable = ['assistant_conversation_id', 'role', 'content', 'unanswered'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unanswered' => 'boolean'];
    }

    /**
     * @return BelongsTo<AssistantConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }
}
