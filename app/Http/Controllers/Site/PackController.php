<?php

namespace App\Http\Controllers\Site;

use App\Enums\FormationKind;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\User;
use App\Packs\PacksPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page « Accompagnement » (/accompagnement) : les offres d'accompagnement, du niveau 1 au niveau 3.
 *
 * Tant qu'aucune offre n'est publiée, la page n'existe pas pour le public (404). L'admin la voit quand même,
 * avec les offres masquées et un bandeau, pour la relire avant de publier.
 */
class PackController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User|null $user */
        $user = $request->user();

        $packs = Formation::query()->publishedPacks()->with('includedFormations')->get();
        $preview = false;

        if ($packs->isEmpty() && $user?->isAdmin()) {
            $packs = Formation::query()
                ->where('kind', FormationKind::Pack->value)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with('includedFormations')
                ->get();
            $preview = true;
        }

        abort_if($packs->isEmpty(), 404);

        return view('site.packs', [
            'packs' => $packs,
            'preview' => $preview,
            'posterUrl' => PacksPage::posterUrl(),
            'owned' => $user ? $user->enrollments()->whereIn('formation_id', $packs->modelKeys())->pluck('formation_id')->all() : [],
        ]);
    }
}
