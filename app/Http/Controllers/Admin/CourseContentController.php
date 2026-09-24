<?php

namespace App\Http\Controllers\Admin;

use App\Courses\Positions;
use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\Formation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * « Contenu des formations » : choix d'une formation, puis gestion de ses modules.
 */
class CourseContentController extends Controller
{
    public function index(): View
    {
        return view('site.admin.content.index', [
            'formations' => Formation::query()
                ->orderBy('sort_order')
                ->withCount(['modules', 'lessons', 'lessons as published_lessons_count' => fn ($query) => $query->where('is_published', true)])
                ->get(),
        ]);
    }

    public function show(Formation $formation): View
    {
        $formation->load(['modules.lessons.resources']);

        return view('site.admin.content.show', ['formation' => $formation]);
    }

    public function storeModule(Request $request, Formation $formation): RedirectResponse
    {
        $validated = $request->validateWithBag('newModule', [
            'title' => ['required', 'string', 'max:255'],
        ], ['title.required' => 'Donnez un titre au module.']);

        $module = $formation->modules()->create([
            'title' => trim((string) $validated['title']),
            'position' => (int) $formation->modules()->max('position') + 1,
        ]);

        return to_route('admin.content.show', $formation)->withFragment('module-'.$module->id)->with('status', 'Module « '.$module->title.' » ajouté.');
    }

    public function updateModule(Request $request, CourseModule $module): RedirectResponse
    {
        $validated = $request->validateWithBag('module'.$module->id, [
            'title' => ['required', 'string', 'max:255'],
        ], ['title.required' => 'Donnez un titre au module.']);

        $module->update(['title' => trim((string) $validated['title'])]);

        return to_route('admin.content.show', $module->formation)->withFragment('module-'.$module->id)->with('status', 'Module renommé.');
    }

    public function moveModule(CourseModule $module, string $direction): RedirectResponse
    {
        Positions::move($module->formation->modules()->get(), $module, $direction);

        return to_route('admin.content.show', $module->formation)->withFragment('module-'.$module->id);
    }

    public function destroyModule(CourseModule $module): RedirectResponse
    {
        $formation = $module->formation;
        $title = $module->title;
        $module->delete();

        return to_route('admin.content.show', $formation)->with('status', 'Module « '.$title.' » supprimé, avec ses leçons.');
    }
}
