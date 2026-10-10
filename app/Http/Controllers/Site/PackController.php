<?php

namespace App\Http\Controllers\Site;

use App\Enums\FormationKind;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\User;
use App\Packs\PacksPage;
use App\Support\Testimonials;
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

        // Offres qu'on peut acheter maintenant (publiées, inscriptions ouvertes) : boutons du haut, du bas et barre fixe.
        $open = $preview ? collect() : $packs->reject(fn (Formation $pack): bool => $pack->registrationsClosed())->values();
        // Compte à rebours du haut de page : la date de fin la plus proche parmi elles.
        $countdownPack = $open->filter(fn (Formation $pack): bool => $pack->registrationDeadline() !== null)
            ->sortBy(fn (Formation $pack): int => (int) $pack->registrationDeadline()?->getTimestampMs())
            ->first();
        // Les boutons communs se masquent à la fin du compte à rebours seulement si toutes ces offres ont une date :
        // à la plus lointaine.
        $closesAt = $open->isNotEmpty() && $open->every(fn (Formation $pack): bool => $pack->registrationDeadline() !== null)
            ? $open->max(fn (Formation $pack): int => (int) $pack->registrationDeadline()?->getTimestampMs())
            : null;

        return view('site.packs', [
            'packs' => $packs,
            'open' => $open->isNotEmpty(),
            'allClosed' => ! $preview && $open->isEmpty(),
            'countdownPack' => $countdownPack,
            'closesAt' => $closesAt,
            'preview' => $preview,
            'posterUrl' => PacksPage::posterUrl(),
            // Mêmes témoignages que l'accueil et les pages des formations (gérés dans l'admin, Témoignages).
            'testimonials' => Testimonials::all(),
            'owned' => $user ? $user->enrollments()->whereIn('formation_id', $packs->modelKeys())->pluck('formation_id')->all() : [],
        ]);
    }
}
