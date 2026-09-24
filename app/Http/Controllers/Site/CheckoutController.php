<?php

namespace App\Http\Controllers\Site;

use App\Actions\Payments\SubmitManualPayment;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentProofRequest;
use App\Models\Formation;
use App\Models\User;
use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Récapitulatif de commande et paiement Mobile Money avec preuve de paiement.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, Formation $formation): View
    {
        abort_unless($formation->is_active, 404);

        /** @var User|null $user */
        $user = $request->user();

        // Un visiteur qui crée son compte ou se connecte depuis cette page y revient ensuite automatiquement.
        if (! $user) {
            $request->session()->put('url.intended', route('checkout.show', $formation));
        }

        $unverified = $user?->purchases()->awaitingVerification()->with('formation')->latest()->first();

        return view('site.checkout.show', [
            'formation' => $formation,
            'alreadyEnrolled' => $user?->isEnrolledIn($formation) ?? false,
            'unverifiedPurchase' => $unverified,
            'countries' => PaymentMethods::countries(),
            'methods' => PaymentMethods::enabled()->map(fn (PaymentMethod $method): array => $method->toArray())->values()->all(),
            'needsVerification' => $formation->price >= PaymentMethods::verificationThreshold(),
        ]);
    }

    public function pay(StorePaymentProofRequest $request, Formation $formation, SubmitManualPayment $submit): RedirectResponse
    {
        abort_unless($formation->is_active, 404);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $proof */
        $proof = $request->file('proof');

        $purchase = $submit->handle($user, $formation, [
            'payment_method' => $request->string('payment_method')->toString(),
            'payer_phone' => $request->string('payer_phone')->toString(),
            'payer_name' => $request->string('payer_name')->toString(),
            'transaction_id' => $request->string('transaction_id')->toString(),
        ], $proof);

        return to_route('student.purchases.show', $purchase)->with('status', $purchase->status === PurchaseStatus::Provisional
            ? 'Merci ! Votre preuve de paiement est bien reçue et votre formation est ouverte.'
            : 'Merci ! Votre preuve de paiement est bien reçue.');
    }
}
