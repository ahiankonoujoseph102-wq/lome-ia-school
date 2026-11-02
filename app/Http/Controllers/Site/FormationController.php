<?php

namespace App\Http\Controllers\Site;

use App\Enums\FormationCategory;
use App\FreeRegistrations\FreeRegistrar;
use App\FreeRegistrations\PendingFreeChoice;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FormationController extends Controller
{
    public function index(): View
    {
        return view('site.formations.index', [
            'formations' => Formation::published()->get(),
            'categories' => FormationCategory::cases(),
        ]);
    }

    public function show(Request $request, Formation $formation, FreeRegistrar $registrar): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        // Groupe seul (Groupe VIP) : aucune page publique.
        abort_if($formation->isGroupOnly(), 404);

        // Offre d'accompagnement : présentée avec les autres offres sur /accompagnement.
        if ($formation->isPack()) {
            // Offre masquée (retirée du site en novembre 2026) : ses liens circulent encore, ils mènent au catalogue.
            if (! $formation->is_active && ! $user?->isAdmin()) {
                return redirect()->route('formations.index');
            }

            return redirect(route('packs').'#'.$formation->slug);
        }

        // Une formation gratuite masquée (brouillon) reste visible par l'admin, pour la relire avant de la publier.
        // Formation masquée : ses liens peuvent encore circuler (WhatsApp, affiliation), ils mènent au catalogue
        // plutôt qu'à une page d'erreur. Une formation gratuite masquée reste visible par l'admin, pour la relire.
        if (! $formation->is_active && ! ($formation->isFree() && $user?->isAdmin())) {
            return redirect()->route('formations.index');
        }

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

        if ($formation->isFree()) {
            return $this->free($request, $formation, $registrar, $related);
        }

        return view('site.formations.show', [
            'formation' => $formation,
            'related' => $related,
            'nextSession' => LiveSession::query()->upcoming()->where('formation_id', $formation->id)->first(),
            'hasAccess' => (bool) auth()->user()?->isEnrolledIn($formation),
        ]);
    }

    /**
     * Page d'une formation gratuite : inscription avec choix d'une option à places limitées (aucun paiement).
     *
     * @param  Collection<int, Formation>  $related
     */
    private function free(Request $request, Formation $formation, FreeRegistrar $registrar, Collection $related): View
    {
        /** @var User|null $user */
        $user = $request->user();

        // Formation commencée avant la création du compte (« Créer mon compte », ou ancien choix avec option) :
        // après la connexion ou la confirmation de l'e-mail, la suite s'ouvre toute seule.
        $choice = app(PendingFreeChoice::class)->current($request, $user);
        $choice = $choice !== null && $choice['formation']->is($formation) ? $choice : null;

        // Un visiteur qui crée son compte ou se connecte depuis cette page y revient ensuite automatiquement.
        if (! $user) {
            $request->session()->put('url.intended', $choice !== null
                ? route('free-registration.complete')
                : route('formations.show', $formation).'#inscription');
        }

        $options = $formation->freeOptions()->withTakenCount()->get();

        return view('site.formations.free', [
            'formation' => $formation,
            'related' => $related,
            'options' => $options,
            'availableOptions' => $options->reject->isFull()->values(),
            'registration' => $registrar->activeFor($user, $formation),
            'closed' => $formation->freeRegistrationsClosed(),
        ]);
    }
}
