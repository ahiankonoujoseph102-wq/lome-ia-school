<?php

namespace App\Scholarships;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Mail\ScholarshipMail;
use App\Models\Purchase;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Services\PurchaseManager;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Décisions de Joseph sur une candidature à la bourse (admin uniquement).
 *
 * - Valider (paiement vérifié dans son historique Mobile Money) : c'est seulement maintenant que le compte est créé
 *   avec l'adresse de la candidature (ou relié au compte qui l'a déjà). Un achat « validé » des frais d'inscription
 *   est enregistré (visible dans les ventes), l'accès à la formation est ouvert et la personne est ajoutée au groupe
 *   (PurchaseManager::grantAccess, comme un achat validé). E-mail « Tu es retenu(e) » avec un lien pour choisir son
 *   mot de passe ; message WhatsApp prêt à envoyer dans l'admin.
 * - Refuser : la place est libérée. « Paiement non reçu » (capture fausse ou paiement introuvable) : rien à
 *   rembourser. « Rembourser » : paiement bien reçu, à rembourser par Joseph, puis « Marquer comme remboursé ».
 */
class ScholarshipDecisions
{
    public function __construct(private PurchaseManager $purchases) {}

    public function validate(ScholarshipApplication $application, User $admin): ScholarshipApplication
    {
        $formation = ScholarshipCampaign::formation();

        if ($formation === null) {
            throw ValidationException::withMessages(['decision' => 'La formation de la bourse est introuvable : vérifiez les réglages de la bourse.']);
        }

        $created = false;

        $application = DB::transaction(function () use ($application, $admin, $formation, &$created): ScholarshipApplication {
            /** @var ScholarshipApplication $application */
            $application = ScholarshipApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $application->isReserved()) {
                throw ValidationException::withMessages(['decision' => 'Cette candidature a déjà été traitée.']);
            }

            $user = User::query()->whereRaw('lower(email) = ?', [Str::lower($application->email)])->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $application->name,
                    'email' => Str::lower($application->email),
                    // Numéro déjà utilisé par un autre compte : le compte est créé sans numéro (modifiable dans le profil).
                    'phone' => User::query()->where('phone', $application->phone)->exists() ? null : $application->phone,
                    // Mot de passe aléatoire jamais communiqué : la personne choisit le sien avec le lien de l'e-mail.
                    'password' => Str::password(40),
                ]);
                // L'adresse est celle de la candidature, où arrive le lien d'accès : considérée comme confirmée.
                $user->forceFill(['email_verified_at' => now()])->save();
                $created = true;
            }

            $purchaseId = null;

            if (! $user->isEnrolledIn($formation)) {
                $purchase = Purchase::query()->create([
                    'user_id' => $user->id,
                    'formation_id' => $formation->id,
                    'amount' => $application->amount,
                    'currency' => 'XOF',
                    'gateway' => PaymentGateway::ManualMobileMoney,
                    'payment_method' => $application->payment_method,
                    'payment_method_name' => $application->payment_method_name,
                    'payment_number' => $application->payment_number,
                    'country' => $application->country,
                    'proof_path' => $application->proof_path,
                    'status' => PurchaseStatus::Validated,
                    'affiliate_code' => null,
                ]);
                $purchase->forceFill([
                    'original_amount' => ScholarshipCampaign::value() > 0 ? ScholarshipCampaign::value() : $formation->price,
                    'manual_reason' => 'Bourse « '.$formation->title.' » (candidature n° '.$application->id.')',
                    'verified_by' => $admin->id,
                    'verified_at' => now(),
                ])->save();

                // Accès à la formation et ajout au groupe, comme un achat validé.
                $this->purchases->grantAccess($purchase);
                $purchaseId = $purchase->id;
            }

            $application->forceFill([
                'status' => ScholarshipApplication::VALIDATED,
                'user_id' => $user->id,
                'purchase_id' => $purchaseId,
                'decided_at' => now(),
                'decided_by' => $admin->id,
            ])->save();

            return $application;
        });

        ActivityLog::record(
            AdminAction::ScholarshipValidated,
            'Bourse : candidature de '.$application->name.' validée ('.$application->genderLabel().', '.Money::fcfa($application->amount).', compte '.($created ? 'créé' : 'existant').').',
            $application,
            by: $admin,
        );

        $this->mail($application, ScholarshipMail::VALIDATED, ['newAccount' => $created]);

        return $application;
    }

    public function refuse(ScholarshipApplication $application, User $admin, bool $refund): ScholarshipApplication
    {
        $application = DB::transaction(function () use ($application, $admin, $refund): ScholarshipApplication {
            /** @var ScholarshipApplication $application */
            $application = ScholarshipApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $application->isReserved()) {
                throw ValidationException::withMessages(['decision' => 'Cette candidature a déjà été traitée.']);
            }

            $application->forceFill([
                'status' => ScholarshipApplication::REFUSED,
                'refund_due' => $refund,
                'decided_at' => now(),
                'decided_by' => $admin->id,
            ])->save();

            return $application;
        });

        ActivityLog::record(
            AdminAction::ScholarshipRefused,
            'Bourse : candidature de '.$application->name.' refusée ('.($refund ? 'paiement reçu, à rembourser' : 'paiement non reçu').'). Place libérée.',
            $application,
            by: $admin,
        );

        $this->mail($application, $refund ? ScholarshipMail::REFUSED_REFUND : ScholarshipMail::REFUSED_UNPAID);

        return $application;
    }

    public function markRefunded(ScholarshipApplication $application, User $admin): void
    {
        if (! $application->isRefused() || ! $application->refund_due || $application->refunded_at !== null) {
            throw ValidationException::withMessages(['decision' => 'Aucun remboursement à marquer pour cette candidature.']);
        }

        $application->forceFill(['refunded_at' => now()])->save();

        ActivityLog::record(AdminAction::ScholarshipRefunded, 'Bourse : '.Money::fcfa($application->amount).' remboursés à '.$application->name.'.', $application, by: $admin);
    }

    /**
     * E-mail envoyé juste après la réponse (le lien d'accès n'est jamais écrit dans la file d'attente).
     *
     * @param  array<string, mixed>  $extra
     */
    private function mail(ScholarshipApplication $application, string $kind, array $extra = []): void
    {
        defer(function () use ($application, $kind, $extra): void {
            try {
                Mail::to($application->email)->send(new ScholarshipMail($application, $kind, $extra));
            } catch (Throwable $exception) {
                Log::warning('Bourse : e-mail non envoyé.', ['application' => $application->id, 'kind' => $kind, 'error' => $exception->getMessage()]);
            }
        });
    }
}
