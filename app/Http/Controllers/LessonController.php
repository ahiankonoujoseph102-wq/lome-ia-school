<?php

namespace App\Http\Controllers;

use App\Courses\CourseProgress;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lecteur de cours : page d'une leçon, leçon terminée, téléchargement des ressources.
 */
class LessonController extends Controller
{
    public function __construct(private CourseProgress $progress) {}

    public function show(Request $request, Formation $formation, Lesson $lesson): View|RedirectResponse
    {
        $this->ensureBelongsTo($formation, $lesson);

        /** @var User|null $user */
        $user = $request->user();

        // Visiteur sur une leçon réservée : connexion, puis retour ici.
        if (! $user && ! $lesson->is_preview) {
            return redirect()->guest(route('login'));
        }

        Gate::authorize('view', $lesson);

        $hasAccess = $user !== null && ($user->isAdmin() || $user->isEnrolledIn($formation));
        $tracked = $user !== null && Gate::allows('track', $lesson);

        if ($tracked) {
            $this->progress->recordView($user, $lesson);
        }

        // Navigation : toutes les leçons publiées pour un élève, seulement les aperçus gratuits sinon.
        $navigation = $formation->orderedPublishedLessons()->when(! $hasAccess, fn ($lessons) => $lessons->where('is_preview', true))->values();
        $index = $navigation->search(fn (Lesson $item): bool => $item->id === $lesson->id);
        $lesson->load(['resources', 'module']);

        return view('site.lessons.show', [
            'formation' => $formation,
            'lesson' => $lesson,
            'hasAccess' => $hasAccess,
            'tracked' => $tracked,
            'summary' => $tracked ? $this->progress->summary($user, $formation) : null,
            'curriculum' => $formation->publicCurriculum(),
            'previous' => $index !== false && $index > 0 ? $navigation->get($index - 1) : null,
            'next' => $index !== false ? $navigation->get($index + 1) : null,
            'position' => $index !== false ? $index + 1 : null,
            'count' => $navigation->count(),
        ]);
    }

    public function complete(Request $request, Formation $formation, Lesson $lesson): RedirectResponse
    {
        $this->ensureBelongsTo($formation, $lesson);
        Gate::authorize('track', $lesson);

        /** @var User $user */
        $user = $request->user();
        $completed = $request->boolean('completed', true);
        $this->progress->setCompleted($user, $lesson, $completed);

        return to_route('lessons.show', [$formation, $lesson])->with('status', $completed ? 'Leçon marquée comme terminée.' : 'Leçon marquée comme non terminée.');
    }

    /**
     * Ressource d'une leçon : mêmes droits que la leçon, jamais mise en cache.
     */
    public function download(Formation $formation, Lesson $lesson, LessonResource $resource): StreamedResponse
    {
        $this->ensureBelongsTo($formation, $lesson);
        abort_unless($resource->lesson_id === $lesson->id, 404);
        Gate::authorize('view', $lesson);

        $disk = Storage::disk(LessonResource::DISK);
        abort_unless($disk->exists($resource->path), 404);

        return $disk->download($resource->path, $resource->name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureBelongsTo(Formation $formation, Lesson $lesson): void
    {
        abort_unless($lesson->module->formation_id === $formation->id, 404);
    }
}
