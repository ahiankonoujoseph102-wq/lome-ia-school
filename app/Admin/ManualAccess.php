<?php

namespace App\Admin;

use App\Enums\AdminAction;
use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Events\PaymentValidated;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Accès manuel : l'admin ouvre une formation à un élève qui a payé hors du site (espèces, WhatsApp…).
 *
 * Crée un achat « validé » marqué « manuel » (visible dans les ventes, avec son reçu), qui déclenche
 * les mêmes événements qu'un paiement validé (groupe de discussion, sessions, e-mail avec reçu),
 * mais jamais de commission d'affiliation.
 */
class ManualAccess
{
    public function __construct(private PurchaseManager $purchases) {}

    public function grant(User $student, Formation $formation, int $amount, string $method, string $reason, User $admin): Purchase
    {
        if (! array_key_exists($method, PaymentGateway::manualMethods())) {
            throw ValidationException::withMessages(['method' => 'Choisissez le moyen de paiement.']);
        }

        $purchase = DB::transaction(function () use ($student, $formation, $amount, $method, $reason, $admin): Purchase {
            User::query()->whereKey($student->id)->lockForUpdate()->first();

            if ($student->isEnrolledIn($formation)) {
                throw ValidationException::withMessages(['formation_id' => $student->name.' a déjà accès à « '.$formation->title.' ».']);
            }

            $purchase = Purchase::query()->create([
                'user_id' => $student->id,
                'formation_id' => $formation->id,
                'amount' => $amount,
                'currency' => 'XOF',
                'gateway' => PaymentGateway::Manual,
                'payment_method' => $method,
                'payment_method_name' => PaymentGateway::manualMethods()[$method],
                'status' => PurchaseStatus::Validated,
                'affiliate_code' => null,
            ]);
            $purchase->forceFill([
                'original_amount' => $formation->price,
                'manual_reason' => trim($reason),
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ])->save();

            $this->purchases->grantAccess($purchase);

            return $purchase;
        });

        // Mêmes suites qu'un paiement validé (reçu par e-mail…) ; la commission est exclue pour un accès manuel.
        PaymentValidated::dispatch($purchase);

        ActivityLog::record(
            AdminAction::ManualAccess,
            'Accès manuel à « '.$formation->title.' » pour '.$student->name.' : '.Money::fcfa($amount).' ('.PaymentGateway::manualMethods()[$method].'). Motif : '.trim($reason),
            $purchase,
            by: $admin,
        );

        return $purchase;
    }
}
