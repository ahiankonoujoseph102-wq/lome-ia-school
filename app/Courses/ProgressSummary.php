<?php

namespace App\Courses;

use App\Models\Lesson;
use App\Models\LessonView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Progression d'un élève dans une formation.
 */
final readonly class ProgressSummary
{
    /**
     * @param  Collection<int, LessonView>  $views  Consultations, indexées par identifiant de leçon.
     */
    public function __construct(
        public int $total,
        public int $completed,
        public ?Lesson $resumeLesson,
        public Collection $views,
        public ?Carbon $startedAt,
    ) {}

    public function percent(): int
    {
        return $this->total > 0 ? (int) floor($this->completed * 100 / $this->total) : 0;
    }

    public function hasStarted(): bool
    {
        return $this->startedAt !== null;
    }

    public function isCompleted(Lesson $lesson): bool
    {
        return $this->views->get($lesson->id)?->completed_at !== null;
    }

    public function isViewed(Lesson $lesson): bool
    {
        return $this->views->has($lesson->id);
    }
}
