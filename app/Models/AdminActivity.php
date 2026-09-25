<?php

namespace App\Models;

use App\Enums\AdminAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Une ligne du journal d'activité : qui a fait quoi et quand dans l'admin.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AdminAction $action
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class AdminActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'description', 'subject_type', 'subject_id', 'properties'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AdminAction::class,
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
