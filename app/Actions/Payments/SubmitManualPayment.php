<?php

namespace App\Actions\Payments;

use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Events\PaymentSubmitted;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use App\Payments\ProofImageProcessor;
use App\Services\PurchaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Paiement Mobile Money manuel : l'élève a envoyé l'argent sur l'un de nos numéros et dépose sa preuve.
 *
 * Contrôles : formation pas déjà possédée, un seul paiement non vérifié à la fois, identifiant de
 * transaction jamais utilisé, capture jamais envoyée. Sous le seuil de vérification, l'accès est ouvert
 * aussitôt à titre provisoire ; au-delà, il attend la validation de l'équipe.
 */
class SubmitManualPayment
{
    public function __construct(
        private ProofImageProcessor $proofs,
        private PurchaseManager $purchases,
    ) {}

    /**
     * @param  array{payment_method: string, payer_phone: string, payer_name: string, transaction_id: string}  $input
     */
    public function handle(User $user, Formation $formation, array $input, UploadedFile $proof): Purchase
    {
        $method = PaymentMethods::findEnabled($input['payment_method']);

        if (! $method) {
            throw ValidationException::withMessages(['payment_method' => 'Choisissez un moyen de paiement dans la liste.']);
        }

        $transactionId = self::normalizeTransactionId($input['transaction_id']);
        $payerPhone = self::normalizePhone($input['payer_phone'], $method);
        $proofHash = $this->proofs->hash($proof);

        if ($payerPhone === null) {
            throw ValidationException::withMessages(['payer_phone' => 'Le numéro qui a payé n\'est pas valide. Exemple : 90 12 34 56.']);
        }

        $this->ensureCanPay($user, $formation);
        $this->ensureProofIsNew($transactionId, $proofHash);

        $path = $this->proofs->store($proof, $user->id);

        try {
            $purchase = DB::transaction(function () use ($user, $formation, $method, $transactionId, $payerPhone, $input, $path, $proofHash): Purchase {
                // Verrou sur l'élève : deux envois simultanés ne peuvent pas passer tous les deux.
                User::query()->whereKey($user->id)->lockForUpdate()->first();
                $this->ensureCanPay($user, $formation);

                $status = $formation->price < PaymentMethods::verificationThreshold()
                    ? PurchaseStatus::Provisional
                    : PurchaseStatus::Pending;

                $purchase = Purchase::create([
                    'user_id' => $user->id,
                    'formation_id' => $formation->id,
                    'amount' => $formation->price,
                    'currency' => 'XOF',
                    'gateway' => PaymentGateway::ManualMobileMoney,
                    'payment_method' => $method->key,
                    'payment_method_name' => $method->name,
                    'payment_number' => $method->number,
                    'payment_beneficiary' => $method->beneficiary,
                    'country' => $method->country,
                    'payer_phone' => $payerPhone,
                    'payer_name' => trim($input['payer_name']),
                    'transaction_id' => $transactionId,
                    'proof_path' => $path,
                    'proof_hash' => $proofHash,
                    'status' => $status,
                ]);

                if ($status === PurchaseStatus::Provisional) {
                    $this->purchases->grantAccess($purchase);
                }

                return $purchase;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk(ProofImageProcessor::DISK)->delete($path);
            $this->ensureProofIsNew($transactionId, $proofHash);

            throw ValidationException::withMessages(['transaction_id' => 'Ce paiement a déjà été envoyé.']);
        } catch (ValidationException $exception) {
            Storage::disk(ProofImageProcessor::DISK)->delete($path);

            throw $exception;
        }

        PaymentSubmitted::dispatch($purchase);

        return $purchase;
    }

    /**
     * Identifiant de transaction comparable : sans espaces, en majuscules.
     */
    public static function normalizeTransactionId(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $value));
    }

    /**
     * Numéro au format international. Un numéro local reçoit l'indicatif du pays du moyen de paiement
     * (le 0 initial est conservé : il fait partie des numéros au Burkina Faso et au Bénin).
     */
    public static function normalizePhone(string $value, PaymentMethod $method): ?string
    {
        $digits = (string) preg_replace('/[^\d+]/', '', trim($value));

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        if (! str_starts_with($digits, '+')) {
            $digits = (string) config("site.payments.countries.{$method->country}.dial_code", '+228').$digits;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $digits) ? $digits : null;
    }

    private function ensureCanPay(User $user, Formation $formation): void
    {
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

    private function ensureProofIsNew(string $transactionId, string $proofHash): void
    {
        if (Purchase::query()->where('transaction_id', $transactionId)->exists()) {
            throw ValidationException::withMessages(['transaction_id' => 'Cet identifiant de transaction a déjà été utilisé. Vérifiez-le sur votre SMS de confirmation.']);
        }

        if (Purchase::query()->where('proof_hash', $proofHash)->exists()) {
            throw ValidationException::withMessages(['proof' => 'Cette capture d\'écran a déjà été envoyée. Envoyez la capture de votre propre paiement.']);
        }
    }
}
