<?php

namespace App\Actions\Payments;

use App\Affiliates\ReferralTracker;
use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Enums\RegistrationStatus;
use App\Events\PaymentSubmitted;
use App\LiveSessions\SessionRegistrar;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\Purchase;
use App\Models\User;
use App\Packs\PackDeadline;
use App\Payments\PaymentMethods;
use App\Payments\ProofImageProcessor;
use App\Promotions\PromoCodes;
use App\Services\PurchaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Paiement Mobile Money manuel : l'élève a envoyé l'argent sur l'un de nos numéros et dépose sa preuve.
 *
 * L'élève n'envoie que la capture de son paiement (aucun numéro, nom ni identifiant de transaction à
 * recopier) : l'équipe lit ces informations sur la capture au moment de la vérification.
 *
 * Contrôles : inscriptions à l'offre d'accompagnement encore ouvertes (App\Packs\PackDeadline), formation pas déjà possédée, un seul paiement non vérifié à la fois, capture jamais envoyée. Sous le seuil de vérification, l'accès est ouvert
 * aussitôt à titre provisoire ; au-delà, il attend la validation de l'équipe.
 *
 * Même parcours pour le supplément d'une session en présentiel ($session) : la place réservée est
 * confirmée à titre provisoire sous le seuil, ou gardée réservée pendant la vérification au-delà.
 */
class SubmitManualPayment
{
    public function __construct(
        private ProofImageProcessor $proofs,
        private PurchaseManager $purchases,
        private SessionRegistrar $sessions,
        private ReferralTracker $referrals,
        private PromoCodes $promoCodes,
    ) {}

    /**
     * @param  array{payment_method: string, promo_code?: string|null}  $input
     */
    public function handle(User $user, Formation $formation, array $input, UploadedFile $proof, ?LiveSession $session = null): Purchase
    {
        // Formation gratuite : jamais d'achat (inscription gratuite, voir App\FreeRegistrations\FreeRegistrar).
        if ($session === null && $formation->isFree()) {
            throw ValidationException::withMessages(['proof' => 'Cette formation est gratuite : aucun paiement n\'est nécessaire.']);
        }

        // Groupe seul (Groupe VIP) : jamais vendu, il s'obtient avec une offre d'accompagnement.
        if ($formation->isGroupOnly()) {
            throw ValidationException::withMessages(['formation' => 'Ce groupe est réservé aux acheteurs des offres d\'accompagnement.']);
        }

        // Inscriptions closes (offre d'accompagnement) : refus avant tout autre contrôle (code promo, capture).
        if ($session === null && $formation->registrationsClosed()) {
            throw ValidationException::withMessages(['formation' => PackDeadline::CLOSED_MESSAGE]);
        }

        $amount = $session !== null ? (int) $session->supplement_amount : $formation->price;
        // Code promo (formations uniquement) : vérifié une première fois ici, puis sous verrou au moment d'enregistrer.
        $promoInput = $session === null ? trim((string) ($input['promo_code'] ?? '')) : '';

        if ($promoInput !== '') {
            $this->promoCodes->quote($promoInput, $formation, $user);
        }

        $method = PaymentMethods::findEnabled($input['payment_method']);

        if (! $method) {
            throw ValidationException::withMessages(['payment_method' => 'Choisissez un moyen de paiement dans la liste.']);
        }

        $proofHash = $this->proofs->hash($proof);

        $this->ensureCanPay($user, $formation, $session);
        $this->ensureProofIsNew($proofHash);

        // Dernier lien d'affiliation cliqué (jamais le propre lien de l'acheteur).
        $affiliateCode = $this->referrals->codeFor($user, request());

        $path = $this->proofs->store($proof, $user->id);

        try {
            $purchase = DB::transaction(function () use ($user, $formation, $session, $amount, $method, $path, $proofHash, $affiliateCode, $promoInput): Purchase {
                // Verrou sur l'élève : deux envois simultanés ne peuvent pas passer tous les deux.
                User::query()->whereKey($user->id)->lockForUpdate()->first();
                $this->ensureCanPay($user, $formation, $session);

                // Verrou sur le code promo : sa limite d'utilisations n'est jamais dépassée.
                $quote = $promoInput !== '' ? $this->promoCodes->quote($promoInput, $formation, $user, lock: true) : null;
                $amount = $quote?->amount() ?? $amount;

                // Seuil d'accès provisoire appliqué au montant réellement payé (remise comprise).
                $status = $amount < PaymentMethods::verificationThreshold()
                    ? PurchaseStatus::Provisional
                    : PurchaseStatus::Pending;

                $purchase = Purchase::create([
                    'user_id' => $user->id,
                    'formation_id' => $formation->id,
                    'live_session_id' => $session?->id,
                    'amount' => $amount,
                    'currency' => 'XOF',
                    'gateway' => PaymentGateway::ManualMobileMoney,
                    'payment_method' => $method->key,
                    'payment_method_name' => $method->name,
                    'payment_number' => $method->number,
                    'payment_beneficiary' => $method->beneficiary,
                    'country' => $method->country,
                    'proof_path' => $path,
                    'proof_hash' => $proofHash,
                    'status' => $status,
                    'affiliate_code' => $affiliateCode,
                    'original_amount' => $quote?->originalAmount,
                    'discount_amount' => $quote?->discount,
                    'promo_code_id' => $quote?->code->id,
                ]);

                if ($session !== null) {
                    $this->sessions->attachPayment($purchase);
                } elseif ($status === PurchaseStatus::Provisional) {
                    $this->purchases->grantAccess($purchase);
                }

                return $purchase;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk(ProofImageProcessor::DISK)->delete($path);
            $this->ensureProofIsNew($proofHash);

            throw ValidationException::withMessages(['proof' => 'Ce paiement a déjà été envoyé.']);
        } catch (ValidationException $exception) {
            Storage::disk(ProofImageProcessor::DISK)->delete($path);

            throw $exception;
        }

        PaymentSubmitted::dispatch($purchase);

        return $purchase;
    }

    private function ensureCanPay(User $user, Formation $formation, ?LiveSession $session = null): void
    {
        if ($session !== null) {
            $this->ensureCanPaySupplement($user, $session);

            return;
        }

        // Offre d'accompagnement dont la date de fin des inscriptions est passée : plus aucune nouvelle commande,
        // même depuis un ancien lien ou une page restée ouverte (vérifié aussi sous verrou, au moment d'enregistrer).
        // Une capture déjà envoyée reste à valider ou refuser par l'équipe (App\Services\PurchaseManager).
        if ($formation->registrationsClosed()) {
            throw ValidationException::withMessages(['formation' => PackDeadline::CLOSED_MESSAGE]);
        }

        $alreadyOwned = $user->isEnrolledIn($formation) || $user->purchases()
            ->where('formation_id', $formation->id)
            ->whereIn('status', [PurchaseStatus::Provisional, PurchaseStatus::Validated])
            ->exists();

        if ($alreadyOwned) {
            throw ValidationException::withMessages(['formation' => 'Vous avez déjà accès à cette formation : inutile de la payer une seconde fois.']);
        }

        if ($user->purchases()->awaitingVerification()->exists()) {
            throw ValidationException::withMessages(['formation' => 'Vous avez déjà un paiement en cours de vérification. Vous pourrez en envoyer un autre dès qu\'il aura été vérifié.']);
        }
    }

    private function ensureCanPaySupplement(User $user, LiveSession $session): void
    {
        $registration = $session->registrations()->where('user_id', $user->id)->first();

        if ($registration === null || $registration->status !== RegistrationStatus::Reserved) {
            throw ValidationException::withMessages(['formation' => $registration?->status === RegistrationStatus::Confirmed
                ? 'Votre place est déjà confirmée : inutile de payer une seconde fois.'
                : 'Aucune place ne vous est réservée pour cette session. Inscrivez-vous d\'abord.']);
        }

        if ($registration->purchase_id !== null) {
            throw ValidationException::withMessages(['formation' => 'Votre paiement pour cette session est déjà en cours de vérification.']);
        }

        if ($user->purchases()->awaitingVerification()->exists()) {
            throw ValidationException::withMessages(['formation' => 'Vous avez déjà un paiement en cours de vérification. Vous pourrez en envoyer un autre dès qu\'il aura été vérifié.']);
        }
    }

    private function ensureProofIsNew(string $proofHash): void
    {
        if (Purchase::query()->where('proof_hash', $proofHash)->exists()) {
            throw ValidationException::withMessages(['proof' => 'Cette capture d\'écran a déjà été envoyée. Envoyez la capture de votre propre paiement.']);
        }
    }
}
