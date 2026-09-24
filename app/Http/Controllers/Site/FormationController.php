<?php

namespace App\Http\Controllers\Site;

use App\Enums\FormationCategory;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Contracts\View\View;

class FormationController extends Controller
{
    public function index(): View
    {
        return view('site.formations.index', [
            'formations' => Formation::published()->get(),
            'categories' => FormationCategory::cases(),
        ]);
    }

    public function show(Formation $formation): View
    {
        abort_unless($formation->is_active, 404);

        $related = Formation::published()
            ->whereKeyNot($formation->getKey())
            ->where('category', $formation->category)
            ->limit(3)
            ->get();

        if ($related->count() < 3) {
            $related = $related->concat(
                Formation::published()
                    ->whereKeyNot($related->modelKeys())
                    ->whereKeyNot($formation->getKey())
                    ->where('is_featured', true)
                    ->limit(3 - $related->count())
                    ->get()
            );
        }

        return view('site.formations.show', [
            'formation' => $formation,
            'related' => $related,
        ]);
    }
}
