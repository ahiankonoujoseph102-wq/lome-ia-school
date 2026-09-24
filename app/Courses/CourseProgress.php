<?php

namespace App\Courses;

use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\LessonView;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Suivi des élèves : consultations, leçons terminées, progression et point de reprise.
 */
class CourseProgress
{
    public function summary(User $user, Formation $formation): ProgressSummary
    {
        $lessons = $formation->orderedPublishedLessons();
        $views = LessonView::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessons->modelKeys())
            ->get()
            ->keyBy('lesson_id');

        return new ProgressSummary(
            total: $lessons->count(),
            completed: $views->whereNotNull('completed_at')->count(),
            resumeLesson: $this->resumeLesson(array_values($lessons->all()), $views->all()),
            views: $views,
            startedAt: $this->startedAt($user, $formation),
        );
    }

    /**
     * Progression de chaque formation d'une liste d'inscriptions, indexée par identifiant de formation.
     *
     * @param  iterable<Enrollment>  $enrollments
     * @return array<int, ProgressSummary>
     */
    public function forEnrollments(User $user, iterable $enrollments): array
    {
        $summaries = [];

        foreach ($enrollments as $enrollment) {
            $summaries[$enrollment->formation_id] = $this->summary($user, $enrollment->formation);
        }

        return $summaries;
    }

    /**
     * Enregistre la consultation d'une leçon (la première date n'est jamais modifiée).
     */
    public function recordView(User $user, Lesson $lesson): LessonView
    {
        $view = LessonView::query()->firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['first_viewed_at' => now(), 'last_viewed_at' => now()],
        );

        if (! $view->wasRecentlyCreated) {
            $view->forceFill(['last_viewed_at' => now()])->save();
        }

        return $view;
    }

    public function setCompleted(User $user, Lesson $lesson, bool $completed): LessonView
    {
        $view = $this->recordView($user, $lesson);
        $view->forceFill(['completed_at' => $completed ? ($view->completed_at ?? now()) : null])->save();

        return $view;
    }

    /**
     * Date de la toute première leçon consultée dans cette formation (même dépubliée depuis), ou null.
     * Sert à la règle de remboursement : aucune leçon consultée.
     */
    public function startedAt(User $user, Formation $formation): ?Carbon
    {
        $first = LessonView::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $formation->lessons()->select('lessons.id'))
            ->min('first_viewed_at');

        return $first ? Carbon::parse($first) : null;
    }

    /**
     * Dernière leçon consultée si elle n'est pas terminée, sinon la suivante ; première leçon si rien n'a été vu.
     *
     * @param  list<Lesson>  $lessons
     * @param  array<int, LessonView>  $views
     */
    private function resumeLesson(array $lessons, array $views): ?Lesson
    {
        if ($lessons === []) {
            return null;
        }

        $last = collect($views)->sortByDesc(fn (LessonView $view) => $view->last_viewed_at->getTimestamp())->first();

        if (! $last) {
            return $lessons[0];
        }

        foreach ($lessons as $index => $lesson) {
            if ($lesson->id === $last->lesson_id) {
                return $last->completed_at && isset($lessons[$index + 1]) ? $lessons[$index + 1] : $lesson;
            }
        }

        return $lessons[0];
    }
}
