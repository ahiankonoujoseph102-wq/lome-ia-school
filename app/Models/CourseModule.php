<?php

namespace App\Models;

use Database\Factories\CourseModuleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Module d'une formation (groupe de leçons), dans l'ordre choisi par l'admin.
 *
 * @property int $id
 * @property int $formation_id
 * @property string $title
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Formation $formation
 * @property-read Collection<int, Lesson> $lessons
 */
class CourseModule extends Model
{
    /** @use HasFactory<CourseModuleFactory> */
    use HasFactory;

    protected $fillable = ['formation_id', 'title', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    protected static function booted(): void
    {
        // Supprime aussi les fichiers des ressources des leçons du module.
        static::deleting(function (CourseModule $module): void {
            $module->lessons()->each(fn (Lesson $lesson) => $lesson->delete());
        });
    }

    /**
     * @return BelongsTo<Formation, $this>
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }
}
