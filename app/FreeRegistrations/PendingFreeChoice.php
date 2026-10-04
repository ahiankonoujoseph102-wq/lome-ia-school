<?php

namespace App\FreeRegistrations;

use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\FreeRegistrationOption;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Choix d'une formation gratuite (et de son option) fait AVANT d'avoir un compte ou une adresse e-mail confirmée.
 *
 * Le choix est gardé dans la session du visiteur pendant la création du compte ou la connexion, puis aussi
 * dans le cache au nom de la personne dès qu'elle a un compte : un bouton de confirmation ouvert sur un autre
 * appareil termine quand même l'inscription. Aucune place n'est réservée ici : la place n'est prise qu'au moment
 * où l'inscription se termine (FreeRegistrar, avec ses verrous et ses contrôles habituels).
 */
class PendingFreeChoice
{
    public const string SESSION_KEY = 'free_registration.choice';

    /** Durée de conservation du choix au nom de la personne (création du compte, code à recevoir…). */
    public const int CACHE_HOURS = 72;

    public function __construct(private FreeRegistrar $registrar) {}

    public function remember(Request $request, Formation $formation, ?FreeRegistrationOption $option, ?User $user): void
    {
        $choice = ['formation_id' => $formation->id, 'option_id' => $option?->id];

        $request->session()->put(self::SESSION_KEY, $choice);

        if ($user !== null) {
            Cache::put($this->cacheKey($user), $choice, now()->addHours(self::CACHE_HOURS));
        }
    }

    /**
     * Le visiteur vient de créer son compte ou de se connecter : son choix est aussi gardé à son nom.
     */
    public function keepFor(Request $request, User $user): void
    {
        $choice = $request->session()->get(self::SESSION_KEY);

        if (is_array($choice)) {
            Cache::put($this->cacheKey($user), $choice, now()->addHours(self::CACHE_HOURS));
        }
    }

    /**
     * Choix en attente (formation gratuite encore ouverte), ou null.
     *
     * @return array{formation: Formation, option: FreeRegistrationOption|null}|null
     */
    public function current(?Request $request, ?User $user): ?array
    {
        $choice = $request?->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (! is_array($choice) && $user !== null) {
            $choice = Cache::get($this->cacheKey($user));
        }

        if (! is_array($choice) || ! is_int($choice['formation_id'] ?? null)) {
            return null;
        }

        $formation = Formation::query()->whereKey($choice['formation_id'])->first();

        if ($formation === null || ! $formation->isFree()) {
            return null;
        }

        $option = is_int($choice['option_id'] ?? null)
            ? $formation->freeOptions()->whereKey($choice['option_id'])->first()
            : null;

        return ['formation' => $formation, 'option' => $option];
    }

    public function forget(?Request $request, ?User $user): void
    {
        if ($request?->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }

        if ($user !== null) {
            Cache::forget($this->cacheKey($user));
        }
    }

    /**
     * Termine l'inscription choisie (adresse e-mail confirmée). Le choix est oublié dans tous les cas :
     * si l'option est devenue pleine, la personne choisit de nouveau parmi les options restantes.
     *
     * @return array{formation: Formation, registration: FreeRegistration|null, already: bool, error: string|null}|null
     */
    public function complete(?Request $request, User $user): ?array
    {
        $choice = $this->current($request, $user);

        if ($choice === null) {
            return null;
        }

        $this->forget($request, $user);
        $formation = $choice['formation'];

        $existing = $this->registrar->activeFor($user, $formation);

        if ($existing !== null) {
            return ['formation' => $formation, 'registration' => $existing, 'already' => true, 'error' => null];
        }

        try {
            $registration = $this->registrar->register($user, $formation, $choice['option']?->id);
        } catch (ValidationException $exception) {
            $message = $exception->validator->errors()->first('option') ?: 'Choisissez de nouveau votre option.';

            return ['formation' => $formation, 'registration' => null, 'already' => false, 'error' => $message];
        }

        return ['formation' => $formation, 'registration' => $registration, 'already' => false, 'error' => null];
    }

    /**
     * Rappel affiché sur la création du compte, la connexion et la confirmation de l'e-mail.
     */
    public static function reminder(Formation $formation, ?FreeRegistrationOption $option): string
    {
        $what = $formation->event_date !== null
            ? 'à la formation gratuite du '.$formation->event_date->translatedFormat('j F')
            : 'à la formation gratuite « '.$formation->title.' »';

        return 'Vous vous inscrivez '.$what.($option !== null ? ', option : '.$option->label : '').'.';
    }

    private function cacheKey(User $user): string
    {
        return 'free-registration-choice:'.$user->id;
    }
}
