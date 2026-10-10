<?php

namespace App\FreeRegistrations;

use App\Enums\EnrollmentSource;
use App\Events\FormationAccessGranted;
use App\Events\FormationAccessRevoked;
use App\Mail\FreeRegistrationMail;
use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\FreeRegistrationOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Inscription gratuite à une formation à 0 FCFA : aucun achat, aucune capture.
 *
 * - La personne choisit une seule option (places limitées) ; verrou sur l'option pour que deux personnes
 *   ne prennent jamais la dernière place.
 * - L'accès est ouvert avec la source « gratuit » : l'événement FormationAccessGranted l'ajoute au groupe
 *   des inscrits, comme pour un achat.
 * - Annulation : la place est libérée et l'accès « gratuit » retiré (sortie du groupe). Un accès donné par
 *   l'équipe n'est jamais retiré.
 */
class FreeRegistrar
{
    public const string ENDED_REGISTER = 'Cette formation est terminée : les inscriptions sont fermées.';

    public const string ENDED_CANCEL = 'Cette formation est terminée : l\'inscription ne peut plus être annulée.';

    public function register(User $user, Formation $formation, ?int $optionId): FreeRegistration
    {
        if (! $formation->isFree() || ! $formation->is_active) {
            throw ValidationException::withMessages(['option' => 'Cette formation n\'est pas ouverte à l\'inscription gratuite.']);
        }

        if ($formation->isFreeEnded()) {
            throw ValidationException::withMessages(['option' => self::ENDED_REGISTER]);
        }

        if ($formation->freeRegistrationsClosed()) {
            throw ValidationException::withMessages(['option' => 'Les inscriptions à cette formation sont closes.']);
        }

        $hasOptions = $formation->freeOptions()->exists();

        if ($hasOptions && $optionId === null) {
            throw ValidationException::withMessages(['option' => 'Choisissez une option.']);
        }

        [$registration, $enrollment] = DB::transaction(function () use ($user, $formation, $optionId, $hasOptions): array {
            // Verrou sur la personne : deux envois simultanés ne créent jamais deux inscriptions.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $registration = FreeRegistration::query()->firstOrNew(['formation_id' => $formation->id, 'user_id' => $user->id]);

            if ($registration->exists && $registration->isActive()) {
                throw ValidationException::withMessages(['option' => 'Vous êtes déjà inscrit(e) à cette formation. Pour changer d\'option, annulez d\'abord votre inscription.']);
            }

            $option = null;

            if ($hasOptions) {
                // Verrou sur l'option : la dernière place ne peut être prise qu'une fois.
                $option = FreeRegistrationOption::query()
                    ->where('formation_id', $formation->id)
                    ->lockForUpdate()
                    ->find($optionId);

                if ($option === null) {
                    throw ValidationException::withMessages(['option' => 'Choisissez une option dans la liste.']);
                }

                if ($option->isFull()) {
                    throw ValidationException::withMessages(['option' => 'L\'option « '.$option->label.' » vient d\'être complète. Choisissez-en une autre.']);
                }
            }

            $registration->forceFill([
                'free_registration_option_id' => $option?->id,
                'cancelled_at' => null,
            ])->save();

            return [$registration, $user->enroll($formation, EnrollmentSource::Free)];
        });

        if ($enrollment->wasRecentlyCreated) {
            FormationAccessGranted::dispatch($enrollment);
        }

        Mail::to($user)->queue(new FreeRegistrationMail($registration->load(['formation', 'option']), $user->first_name));

        return $registration;
    }

    public function cancel(User $user, Formation $formation): void
    {
        if ($formation->isFreeEnded()) {
            throw ValidationException::withMessages(['option' => self::ENDED_CANCEL]);
        }

        if ($formation->freeRegistrationsClosed()) {
            throw ValidationException::withMessages(['option' => 'La formation a déjà eu lieu : l\'inscription ne peut plus être annulée.']);
        }

        $revoked = DB::transaction(function () use ($user, $formation): bool {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $registration = FreeRegistration::query()
                ->where('formation_id', $formation->id)
                ->where('user_id', $user->id)
                ->active()
                ->first();

            if ($registration === null) {
                return false;
            }

            $registration->forceFill(['cancelled_at' => now()])->save();

            return $user->enrollments()
                ->where('formation_id', $formation->id)
                ->where('source', EnrollmentSource::Free->value)
                ->delete() > 0;
        });

        if ($revoked) {
            FormationAccessRevoked::dispatch($user, $formation);
        }
    }

    /**
     * Inscription en cours de cette personne, ou null.
     */
    public function activeFor(?User $user, Formation $formation): ?FreeRegistration
    {
        if ($user === null) {
            return null;
        }

        return FreeRegistration::query()
            ->with('option')
            ->where('formation_id', $formation->id)
            ->where('user_id', $user->id)
            ->active()
            ->first();
    }
}
