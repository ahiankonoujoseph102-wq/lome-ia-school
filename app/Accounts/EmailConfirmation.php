<?php

namespace App\Accounts;

use App\Mail\EmailConfirmationMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Confirmation de l'adresse e-mail : un e-mail contient un code à 6 chiffres et un bouton (lien), valables
 * 30 minutes et à usage unique. Un nouvel envoi remplace le précédent (l'ancien code et l'ancien lien ne
 * fonctionnent plus). Code et lien ne sont jamais enregistrés en clair.
 *
 * Limites (par compte) :
 * - envoi : une fois par minute et 5 fois par heure (inscription, « Renvoyer le code », changement d'adresse) ;
 * - saisie du code : 5 erreurs, puis code annulé et saisie bloquée 15 minutes.
 *
 * Tant que l'adresse n'est pas confirmée, l'élève ne peut ni payer (formation ou supplément) ni demander
 * un retrait d'affiliation (middleware « email-confirmed »).
 */
class EmailConfirmation
{
    public const int LIFETIME_MINUTES = 30;

    public const int MAX_ATTEMPTS = 5;

    public const int LOCKOUT_SECONDS = 900;

    public const int SENDS_PER_HOUR = 5;

    /**
     * Crée un nouveau code et un nouveau lien, puis envoie l'e-mail juste après la réponse (l'élève n'attend pas
     * l'envoi, et le code part tout de suite, sans attendre le passage de la file d'attente).
     */
    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(64);

        EmailVerification::query()->updateOrCreate(['user_id' => $user->id], [
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'token_hash' => hash('sha256', $token),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        RateLimiter::hit($this->minuteKey($user), 60);
        RateLimiter::hit($this->hourKey($user), 3600);

        // Jamais par la file d'attente : le code n'est jamais enregistré en clair, même dans la table « jobs ».
        // Un échec d'envoi est noté dans le journal : l'élève peut demander un nouveau code.
        defer(function () use ($user, $code, $token): void {
            try {
                Mail::to($user)->send(new EmailConfirmationMail($user, $code, $token));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * Secondes à attendre avant de pouvoir recevoir un nouveau code (0 : possible tout de suite).
     */
    public function secondsBeforeNextSend(User $user): int
    {
        return max(
            RateLimiter::tooManyAttempts($this->minuteKey($user), 1) ? RateLimiter::availableIn($this->minuteKey($user)) : 0,
            RateLimiter::tooManyAttempts($this->hourKey($user), self::SENDS_PER_HOUR) ? RateLimiter::availableIn($this->hourKey($user)) : 0,
        );
    }

    /**
     * Refuse un nouvel envoi trop rapproché (message d'erreur sur le champ indiqué).
     *
     * @throws ValidationException
     */
    public function ensureCanSend(User $user, string $field = 'resend'): void
    {
        $seconds = $this->secondsBeforeNextSend($user);

        if ($seconds > 0) {
            throw ValidationException::withMessages([$field => 'Vous avez demandé plusieurs codes. Patientez '.self::duration($seconds).' avant d\'en demander un nouveau.']);
        }
    }

    /**
     * Change l'adresse (mal tapée) et envoie un nouveau code à la nouvelle adresse.
     */
    public function changeEmail(User $user, string $email): void
    {
        $user->forceFill(['email' => Str::lower($email), 'email_verified_at' => null])->save();

        $this->send($user);
    }

    /**
     * Secondes restantes du blocage après trop de codes erronés (0 : pas de blocage).
     */
    public function lockoutSeconds(User $user): int
    {
        return RateLimiter::tooManyAttempts($this->attemptsKey($user), self::MAX_ATTEMPTS)
            ? RateLimiter::availableIn($this->attemptsKey($user))
            : 0;
    }

    /**
     * Vérifie le code saisi. Erreurs sur le champ « code ».
     *
     * @throws ValidationException
     */
    public function confirmWithCode(User $user, string $code): void
    {
        $locked = $this->lockoutSeconds($user);

        if ($locked > 0) {
            throw ValidationException::withMessages(['code' => 'Trop de codes erronés. Pour votre sécurité, la saisie est bloquée pendant '.self::duration($locked).'. Demandez ensuite un nouveau code.']);
        }

        $code = (string) preg_replace('/\D+/', '', $code);
        $verification = $this->pending($user);

        if ($verification === null) {
            throw ValidationException::withMessages(['code' => 'Ce code n\'est plus valable (il expire au bout de '.self::LIFETIME_MINUTES.' minutes). Demandez un nouveau code.']);
        }

        if (strlen($code) !== 6 || ! Hash::check($code, $verification->code_hash)) {
            RateLimiter::hit($this->attemptsKey($user), self::LOCKOUT_SECONDS);
            $verification->increment('attempts');

            if ($verification->attempts >= self::MAX_ATTEMPTS || $this->lockoutSeconds($user) > 0) {
                // Trop d'erreurs : le code et le lien de l'e-mail ne fonctionnent plus.
                $verification->delete();

                throw ValidationException::withMessages(['code' => 'Trop de codes erronés. Pour votre sécurité, la saisie est bloquée pendant '.self::duration(self::LOCKOUT_SECONDS).'. Demandez ensuite un nouveau code.']);
            }

            $left = RateLimiter::remaining($this->attemptsKey($user), self::MAX_ATTEMPTS);

            throw ValidationException::withMessages(['code' => 'Code incorrect. Vérifiez les 6 chiffres reçus par e-mail ('.$left.' '.($left > 1 ? 'essais restants' : 'essai restant').').']);
        }

        RateLimiter::clear($this->attemptsKey($user));
        $this->markConfirmed($user);
    }

    /**
     * Bouton de l'e-mail : confirme l'adresse si le lien est valable (30 minutes, une seule fois, adresse inchangée).
     */
    public function confirmWithToken(string $token): ?User
    {
        if (strlen($token) !== 64) {
            return null;
        }

        $verification = EmailVerification::query()->with('user')->where('token_hash', hash('sha256', $token))->first();

        if ($verification === null || $verification->isExpired() || $verification->user->email !== $verification->email) {
            return null;
        }

        $user = $verification->user;
        $this->markConfirmed($user);

        return $user;
    }

    /**
     * Adresse confirmée (par le code, le lien, ou manuellement par l'admin) : code et lien supprimés.
     */
    public function markConfirmed(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['email_verified_at' => now()])->save();
            EmailVerification::query()->where('user_id', $user->id)->delete();
        });
    }

    /**
     * Code en cours, encore valable, envoyé à l'adresse actuelle de l'élève.
     */
    public function pending(User $user): ?EmailVerification
    {
        $verification = EmailVerification::query()->where('user_id', $user->id)->first();

        return $verification !== null && ! $verification->isExpired() && $verification->email === $user->email
            ? $verification
            : null;
    }

    /**
     * « 45 secondes », « 1 minute », « 12 minutes ».
     */
    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' '.($seconds > 1 ? 'secondes' : 'seconde');
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' '.($minutes > 1 ? 'minutes' : 'minute');
    }

    private function minuteKey(User $user): string
    {
        return 'email-confirmation:send-minute:'.$user->id;
    }

    private function hourKey(User $user): string
    {
        return 'email-confirmation:send-hour:'.$user->id;
    }

    private function attemptsKey(User $user): string
    {
        return 'email-confirmation:code:'.$user->id;
    }
}
