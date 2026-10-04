<?php

namespace App\Http\Controllers\Student;

use App\Accounts\EmailConfirmation;
use App\Concerns\ProfileValidationRules;
use App\FreeRegistrations\PendingFreeChoice;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\FreeRegistrationController;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Page « Confirmez votre e-mail » : saisie du code, « Renvoyer le code », correction d'une adresse mal tapée,
 * et bouton de l'e-mail (lien valable 30 minutes, une seule fois, utilisable même sans être connecté).
 */
class EmailConfirmationController extends Controller
{
    use ProfileValidationRules;

    public const string CONFIRMED = 'Merci, votre adresse e-mail est confirmée.';

    public function __construct(private EmailConfirmation $confirmation, private PendingFreeChoice $choices) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasConfirmedEmail()) {
            return to_route('dashboard')->with('status', 'Votre adresse e-mail est déjà confirmée.');
        }

        // Page d'où vient l'élève (commande, paiement d'un supplément, retraits) : il y revient une fois l'adresse confirmée.
        $back = $request->string('retour')->toString();

        if (Str::startsWith($back, '/') && ! Str::startsWith($back, ['//', '/\\'])) {
            $request->session()->put('url.intended', url($back));
        }

        // Option d'une formation gratuite choisie avant la création du compte : gardée aussi au nom de la
        // personne (bouton de l'e-mail ouvert sur un autre appareil).
        $this->choices->keepFor($request, $user);
        $choice = $this->choices->current($request, $user);

        return view('site.student.email-confirmation', [
            'user' => $user,
            'hasPendingCode' => $this->confirmation->pending($user) !== null,
            'resendIn' => $this->confirmation->secondsBeforeNextSend($user),
            'lockedFor' => $this->confirmation->lockoutSeconds($user),
            'minutes' => EmailConfirmation::LIFETIME_MINUTES,
            'freeChoice' => $choice,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasConfirmedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Saisissez le code à 6 chiffres reçu par e-mail.']);

        $this->confirmation->confirmWithCode($user, $request->string('code')->toString());

        return $this->afterConfirmation($request, $user);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasConfirmedEmail()) {
            return to_route('dashboard');
        }

        $this->confirmation->ensureCanSend($user);
        $this->confirmation->send($user);

        return to_route('email-confirmation.show')->with('status', 'Un nouveau code vient d\'être envoyé à '.$user->email.'. L\'ancien code ne fonctionne plus.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasConfirmedEmail()) {
            return to_route('student.profile');
        }

        $request->merge(['email' => Str::lower(trim($request->string('email')->toString()))]);
        $validated = $request->validateWithBag('changeEmail', [
            'email' => $this->emailRules($user->id),
        ], [
            'email.unique' => 'Un autre compte utilise déjà cette adresse e-mail.',
        ]);

        if ($validated['email'] === $user->email) {
            return back()->withErrors(['email' => 'C\'est déjà votre adresse actuelle. Utilisez « Renvoyer le code » si vous n\'avez rien reçu.'], 'changeEmail');
        }

        try {
            $this->confirmation->ensureCanSend($user, 'email');
        } catch (ValidationException $exception) {
            throw $exception->errorBag('changeEmail');
        }

        $this->confirmation->changeEmail($user, (string) $validated['email']);

        return to_route('email-confirmation.show')->with('status', 'Adresse modifiée. Un nouveau code vient d\'être envoyé à '.$user->email.'.');
    }

    /**
     * Bouton de l'e-mail : fonctionne aussi sans être connecté (e-mail ouvert sur un autre appareil).
     */
    public function link(Request $request, string $token): RedirectResponse
    {
        $confirmed = $this->confirmation->confirmWithToken($token);
        $current = Auth::user();

        if ($confirmed === null) {
            $message = 'Ce lien de confirmation n\'est plus valable (il sert une seule fois, pendant '.EmailConfirmation::LIFETIME_MINUTES.' minutes). Si votre adresse est déjà confirmée, il n\'y a rien d\'autre à faire.';

            return $current instanceof User && ! $current->hasConfirmedEmail()
                ? to_route('email-confirmation.show')->withErrors(['code' => $message.' Sinon, demandez un nouveau code.'])
                : ($current instanceof User
                    ? to_route('dashboard')->with('status', 'Votre adresse e-mail est déjà confirmée.')
                    : to_route('login')->with('warning', $message.' Sinon, connectez-vous pour recevoir un nouveau code.'));
        }

        if ($current instanceof User) {
            return $current->is($confirmed)
                ? $this->afterConfirmation($request, $confirmed)
                : to_route('dashboard')->with('status', 'L\'adresse '.$confirmed->email.' est confirmée.');
        }

        // Bouton ouvert sur un autre appareil : l'inscription gratuite choisie avant se termine quand même.
        $result = $this->choices->complete(null, $confirmed);

        if ($result !== null && $result['error'] === null) {
            $request->session()->put('url.intended', route('formations.show', $result['formation']).'#inscription');

            return to_route('login')->with('status', 'Merci, votre adresse e-mail est confirmée et vous êtes inscrit(e) à « '.$result['formation']->title.' ». Connectez-vous pour voir votre inscription.');
        }

        if ($result !== null) {
            $request->session()->put('url.intended', route('formations.show', $result['formation']).'#inscription');

            return to_route('login')->with('warning', 'Votre adresse e-mail est confirmée. '.$result['error'].' Connectez-vous pour choisir.');
        }

        return to_route('login')->with('status', 'Merci, votre adresse e-mail est confirmée. Connectez-vous pour continuer.');
    }

    /**
     * Adresse confirmée : une inscription gratuite choisie avant se termine aussitôt (place prise maintenant,
     * entrée dans le groupe, écran « Vous êtes inscrit(e) ») ; sinon, retour à la page d'où venait l'élève.
     */
    private function afterConfirmation(Request $request, User $user): RedirectResponse
    {
        $result = $this->choices->complete($request, $user);

        if ($result !== null) {
            $request->session()->forget('url.intended');

            return FreeRegistrationController::redirectAfter($result);
        }

        return redirect()->intended(route('dashboard'))->with('status', self::CONFIRMED);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
