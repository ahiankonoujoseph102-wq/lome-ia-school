<?php

namespace App\Http\Controllers\Site;

use App\FreeRegistrations\FreeRegistrar;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Inscription gratuite à une formation à 0 FCFA (compte et adresse e-mail confirmée obligatoires) et annulation.
 */
class FreeRegistrationController extends Controller
{
    public function store(Request $request, Formation $formation, FreeRegistrar $registrar): RedirectResponse
    {
        abort_unless($formation->isFree(), 404);

        $validated = $request->validate(
            ['option' => ['nullable', 'integer']],
            ['option.integer' => 'Choisissez une option dans la liste.'],
        );

        /** @var User $user */
        $user = $request->user();
        $registrar->register($user, $formation, isset($validated['option']) ? (int) $validated['option'] : null);

        return redirect(route('formations.show', $formation).'#inscription')
            ->with('status', 'C\'est noté : vous êtes inscrit(e) ! Un e-mail de confirmation vous a été envoyé.');
    }

    public function destroy(Request $request, Formation $formation, FreeRegistrar $registrar): RedirectResponse
    {
        abort_unless($formation->isFree(), 404);

        /** @var User $user */
        $user = $request->user();
        $registrar->cancel($user, $formation);

        return redirect(route('formations.show', $formation).'#inscription')
            ->with('status', 'Votre inscription est annulée et votre place est libérée.');
    }
}
