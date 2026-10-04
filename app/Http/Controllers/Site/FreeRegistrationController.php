<?php

namespace App\Http\Controllers\Site;

use App\FreeRegistrations\FreeRegistrar;
use App\FreeRegistrations\PendingFreeChoice;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Inscription gratuite à une formation à 0 FCFA (compte et adresse e-mail confirmée obligatoires) et annulation.
 *
 * Parcours d'un visiteur sans compte (ou dont l'adresse n'est pas confirmée) : il choisit d'abord son option
 * (« choose », gardée en mémoire, aucune place réservée), crée son compte ou se connecte, confirme son e-mail,
 * puis l'inscription se termine toute seule (« complete ») : place prise à ce moment-là, entrée dans le groupe.
 */
class FreeRegistrationController extends Controller
{
    public const string CHOOSE_FIRST = 'Choisissez d\'abord une option ci-dessus, puis touchez de nouveau « Je m\'inscris gratuitement ».';

    public const string REGISTERED = 'C\'est noté : vous êtes inscrit(e) ! Un e-mail de confirmation vous a été envoyé.';

    /**
     * Choix de l'option avant d'avoir un compte (ou une adresse confirmée) : gardé en mémoire, puis direction
     * la création du compte, la connexion ou la confirmation de l'e-mail.
     */
    public function choose(Request $request, Formation $formation, PendingFreeChoice $choices): RedirectResponse
    {
        abort_unless($formation->isFree(), 404);

        /** @var User|null $user */
        $user = $request->user();
        $toLogin = $request->input('then') === 'login';
        $page = route('formations.show', $formation).'#inscription';

        if ($user !== null && $user->hasConfirmedEmail()) {
            // Adresse déjà confirmée : inscription habituelle (formulaire de la page).
            return redirect($page);
        }

        $options = $formation->freeOptions()->withTakenCount()->get();
        $option = null;

        if ($options->isNotEmpty()) {
            $option = $options->firstWhere('id', (int) $request->input('option'));

            if ($option === null) {
                if ($toLogin && $user === null) {
                    // « J'ai déjà un compte » sans option : connexion, puis retour sur la page pour choisir.
                    $request->session()->put('url.intended', $page);

                    return to_route('login');
                }

                return redirect($page)->withErrors(['option' => self::CHOOSE_FIRST]);
            }

            if ($option->isFull()) {
                return redirect($page)->withErrors(['option' => 'L\'option « '.$option->label.' » vient d\'être complète. Choisissez-en une autre.']);
            }
        }

        if ($formation->freeRegistrationsClosed()) {
            return redirect($page)->withErrors(['option' => 'Les inscriptions à cette formation sont closes.']);
        }

        $choices->remember($request, $formation, $option, $user);
        $request->session()->put('url.intended', route('free-registration.complete'));

        if ($user !== null) {
            return to_route('free-registration.complete');
        }

        return to_route($toLogin ? 'login' : 'register');
    }

    /**
     * Fin de l'inscription choisie avant la création du compte : après la connexion ou la confirmation de l'e-mail.
     */
    public function complete(Request $request, PendingFreeChoice $choices): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($choices->current($request, $user) === null) {
            return to_route('dashboard');
        }

        if (! $user->hasConfirmedEmail()) {
            // Dernière étape : la confirmation de l'e-mail ; l'inscription se terminera juste après.
            $choices->keepFor($request, $user);
            $request->session()->put('url.intended', route('free-registration.complete'));

            return to_route('email-confirmation.show');
        }

        return self::redirectAfter($choices->complete($request, $user));
    }

    /**
     * Page de la formation après la fin automatique de l'inscription : écran « Vous êtes inscrit(e) », ou
     * message clair et choix parmi les options restantes si l'option est devenue pleine entre-temps.
     *
     * @param  array{formation: Formation, registration: mixed, already: bool, error: string|null}|null  $result
     */
    public static function redirectAfter(?array $result): RedirectResponse
    {
        if ($result === null) {
            return to_route('dashboard');
        }

        $page = redirect(route('formations.show', $result['formation']).'#inscription');

        if ($result['error'] !== null) {
            return $page->withErrors(['option' => $result['error']]);
        }

        return $page->with('status', $result['already'] ? 'Vous êtes déjà inscrit(e) à cette formation.' : self::REGISTERED);
    }

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
            ->with('status', self::REGISTERED);
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
