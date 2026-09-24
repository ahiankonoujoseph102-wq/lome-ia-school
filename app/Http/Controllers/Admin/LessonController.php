<?php

namespace App\Http\Controllers\Admin;

use App\Courses\Positions;
use App\Http\Controllers\Controller;
use App\Http\Requests\LessonRequest;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Video\VideoProviders;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Leçons d'une formation : création, modification, ordre, publication, ressources.
 */
class LessonController extends Controller
{
    public function create(CourseModule $module): View
    {
        return view('site.admin.content.lesson', [
            'formation' => $module->formation->load('modules'),
            'module' => $module,
            'lesson' => new Lesson(['course_module_id' => $module->id, 'is_published' => true]),
            'provider' => VideoProviders::default(),
        ]);
    }

    public function store(LessonRequest $request, CourseModule $module): RedirectResponse
    {
        $data = $request->lessonData();
        /** @var CourseModule $target */
        $target = CourseModule::query()->findOrFail((int) $data['course_module_id']);
        $data['position'] = (int) $target->lessons()->max('position') + 1;

        $lesson = Lesson::create($data);
        $this->storeResources($request, $lesson);

        return to_route('admin.content.show', $target->formation)
            ->withFragment('lecon-'.$lesson->id)
            ->with('status', 'Leçon « '.$lesson->title.' » ajoutée'.($lesson->is_published ? ' et publiée.' : ' (brouillon, non visible par les élèves).'));
    }

    public function edit(Lesson $lesson): View
    {
        $lesson->load(['resources', 'module.formation.modules']);

        return view('site.admin.content.lesson', [
            'formation' => $lesson->module->formation,
            'module' => $lesson->module,
            'lesson' => $lesson,
            'provider' => VideoProviders::default(),
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->lessonData();

        if ($data['course_module_id'] !== $lesson->course_module_id) {
            $data['position'] = (int) Lesson::query()->where('course_module_id', $data['course_module_id'])->max('position') + 1;
        }

        $lesson->update($data);
        $this->storeResources($request, $lesson);

        return to_route('admin.lessons.edit', $lesson)->with('status', 'Leçon enregistrée.');
    }

    public function move(Lesson $lesson, string $direction): RedirectResponse
    {
        Positions::move($lesson->module->lessons()->get(), $lesson, $direction);

        return to_route('admin.content.show', $lesson->module->formation)->withFragment('lecon-'.$lesson->id);
    }

    public function togglePublish(Lesson $lesson): RedirectResponse
    {
        $lesson->update(['is_published' => ! $lesson->is_published]);

        return to_route('admin.content.show', $lesson->module->formation)
            ->withFragment('lecon-'.$lesson->id)
            ->with('status', 'Leçon « '.$lesson->title.' » '.($lesson->is_published ? 'publiée.' : 'dépubliée : elle n\'est plus visible par les élèves.'));
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $formation = $lesson->module->formation;
        $title = $lesson->title;
        $lesson->delete();

        return to_route('admin.content.show', $formation)->with('status', 'Leçon « '.$title.' » supprimée.');
    }

    public function destroyResource(LessonResource $resource): RedirectResponse
    {
        $lesson = $resource->lesson;
        $resource->delete();

        return to_route('admin.lessons.edit', $lesson)->withFragment('ressources')->with('status', 'Fichier « '.$resource->name.' » supprimé.');
    }

    private function storeResources(LessonRequest $request, Lesson $lesson): void
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('resources', []);

        foreach ($files as $file) {
            $name = Str::limit(trim((string) preg_replace('/[^\pL\pN ._()\-]+/u', '_', $file->getClientOriginalName())), 150, '') ?: 'fichier';
            $path = $file->storeAs((string) $lesson->id, Str::lower(Str::random(24)).'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'), LessonResource::DISK);

            $lesson->resources()->create([
                'name' => $name,
                'path' => (string) $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }
    }
}
