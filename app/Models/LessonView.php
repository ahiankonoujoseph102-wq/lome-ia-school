<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Suivi d'un élève sur une leçon : première et dernière consultation, leçon terminée.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property Carbon $first_viewed_at
 * @property Carbon $last_viewed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Lesson $lesson
 */
class LessonView extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'first_viewed_at', 'last_viewed_at', 'completed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
