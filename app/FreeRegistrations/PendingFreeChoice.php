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
 * Choix d'une formation gratuite fait AVANT d'avoir un compte ou une adresse e-mail confirmée.
 *
 * Parcours actuel (« compte d'abord ») : seule la formation est gardée (option vide) ; une fois le compte créé
 * et l'adresse confirmée, la personne arrive directement sur l'écran « Dernière étape : choisissez votre option ».
 * Ancien parcours (option choisie avant le compte) : toujours pris en charge pour les choix déjà gardés en mémoire,
 * l'inscription se termine alors toute seule avec cette option.
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
     * Aucune option gardée alors que la formation en propose (parcours « compte d'abord ») : rien n'est réservé,
     * « choose » demande d'ouvrir l'écran du choix de l'option.
     *
     * @return array{formation: Formation, registration: FreeRegistration|null, already: bool, error: string|null, choose: bool}|null
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
            return ['formation' => $formation, 'registration' => $existing, 'already' => true, 'error' => null, 'choose' => false];
        }

        if ($choice['option'] === null && $formation->freeOptions()->exists()) {
            return ['formation' => $formation, 'registration' => null, 'already' => false, 'error' => null, 'choose' => true];
        }

        try {
            $registration = $this->registrar->register($user, $formation, $choice['option']?->id);
        } catch (ValidationException $exception) {
            $message = $exception->validator->errors()->first('option') ?: 'Choisissez de nouveau votre option.';

            return ['formation' => $formation, 'registration' => null, 'already' => false, 'error' => $message, 'choose' => false];
        }

        return ['formation' => $formation, 'registration' => $registration, 'already' => false, 'error' => null, 'choose' => false];
    }

    /**
     * Rappel affiché sur la création du compte, la connexion et la confirmation de l'e-mail.
     * Sans option (parcours « compte d'abord ») : « Vous créez votre compte pour vous inscrire… ».
     */
    public static function reminder(Formation $formation, ?FreeRegistrationOption $option, string $step = 'register'): string
    {
        $what = self::eventName($formation);

        if ($option !== null) {
            return 'Vous vous inscrivez '.$what.', option : '.$option->label.'.';
        }

        return match ($step) {
            'login' => 'Vous vous connectez pour vous inscrire '.$what.'.',
            'confirm' => 'Vous vous inscrivez '.$what.'.',
            default => 'Vous créez votre compte pour vous inscrire '.$what.'.',
        };
    }

    /**
     * « à la formation gratuite du 10 octobre » (ou son titre s'il n'y a pas de date).
     */
    public static function eventName(Formation $formation): string
    {
        return $formation->event_date !== null
            ? 'à la formation gratuite du '.$formation->event_date->translatedFormat('j F')
            : 'à la formation gratuite « '.$formation->title.' »';
    }

    private function cacheKey(User $user): string
    {
        return 'free-registration-choice:'.$user->id;
    }
}
