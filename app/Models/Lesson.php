<?php

namespace App\Models;

use App\Contracts\VideoProvider;
use App\Video\VideoProviders;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Leçon d'un module : vidéo, description, ressources, aperçu gratuit éventuel.
 *
 * @property int $id
 * @property int $course_module_id
 * @property string $title
 * @property string|null $description
 * @property string|null $video_provider Clé du fournisseur (config/video.php).
 * @property string|null $video_id
 * @property int|null $duration_seconds
 * @property bool $is_preview Visible par tous les visiteurs.
 * @property bool $is_published
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseModule $module
 * @property-read Collection<int, LessonResource> $resources
 * @property-read string|null $formatted_duration
 */
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected $fillable = [
        'course_module_id',
        'title',
        'description',
        'video_provider',
        'video_id',
        'duration_seconds',
        'is_preview',
        'is_published',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'is_preview' => 'boolean',
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Supprime les fichiers des ressources avec la leçon.
        static::deleting(function (Lesson $lesson): void {
            $lesson->resources()->each(fn (LessonResource $resource) => $resource->delete());
        });
    }

    /**
     * @return BelongsTo<CourseModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    /**
     * @return HasMany<LessonResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('id');
    }

    /**
     * @return HasMany<LessonView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(LessonView::class);
    }

    /**
     * @param  Builder<Lesson>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function formation(): Formation
    {
        return $this->module->formation;
    }

    public function videoProvider(): ?VideoProvider
    {
        return VideoProviders::find($this->video_provider);
    }

    public function embedUrl(): ?string
    {
        return $this->video_id ? $this->videoProvider()?->embedUrl($this->video_id) : null;
    }

    /**
     * Durée lisible : « 45 s », « 8 min », « 4 min 30 », « 1 h 05 ».
     *
     * @return Attribute<string|null, never>
     */
    protected function formattedDuration(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->duration_seconds ? self::formatDuration($this->duration_seconds) : null);
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' s';
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        if ($minutes < 60) {
            return $minutes.' min'.($rest > 0 ? ' '.str_pad((string) $rest, 2, '0', STR_PAD_LEFT) : '');
        }

        return intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Durée saisie par l'admin : « 12:30 », « 1:02:10 », « 12 » (minutes), « 12 min ». Null si vide ou illisible.
     */
    public static function parseDuration(?string $input): ?int
    {
        $input = trim(mb_strtolower((string) $input));

        if ($input === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $input, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }

        if (preg_match('/^(\d{1,3}):(\d{2})$/', $input, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        if (preg_match('/^(\d{1,3})\s*(?:min|mn|minutes?)?$/', $input, $m)) {
            return (int) $m[1] * 60;
        }

        return null;
    }
}
