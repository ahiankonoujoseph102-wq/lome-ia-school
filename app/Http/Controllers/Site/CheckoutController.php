<?php

namespace App\Http\Controllers\Site;

use App\Actions\Payments\SubmitManualPayment;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentProofRequest;
use App\Models\Formation;
use App\Models\PromoCode;
use App\Models\User;
use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use App\Promotions\PromoCodes;
use App\Support\Money;
use App\Support\VoiceAssistant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Récapitulatif de commande et paiement Mobile Money avec preuve de paiement.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, Formation $formation, PromoCodes $promoCodes): View|RedirectResponse
    {
        // Un groupe seul (Groupe VIP) ne se vend jamais : il s'obtient avec une offre d'accompagnement.
        abort_unless($formation->is_active && ! $formation->isGroupOnly(), 404);

        // Formation gratuite : aucun paiement, l'inscription se fait sur la page de la formation.
        if ($formation->isFree()) {
            return redirect(route('formations.show', $formation).'#inscription');
        }

        /** @var User|null $user */
        $user = $request->user();
        $code = PromoCode::normalize($request->string('code')->limit(40, '')->toString());
        $quote = null;
        $promoError = null;

        if ($code !== '') {
            try {
                $quote = $promoCodes->quote($code, $formation, $user);
            } catch (ValidationException $exception) {
                $promoError = $exception->validator->errors()->first('promo_code');
            }
        }

        // Un visiteur qui crée son compte ou se connecte depuis cette page y revient ensuite automatiquement (avec son code promo).
        if (! $user) {
            $request->session()->put('url.intended', route('checkout.show', array_filter([$formation, 'code' => $quote ? $code : null])));
        }

        $unverified = $user?->purchases()->awaitingVerification()->with('formation')->latest()->first();
        $amount = $quote?->amount() ?? $formation->price;

        // Contexte transmis à l'assistante Ama (montant réellement à payer, code promo refusé).
        VoiceAssistant::addContext($request, array_filter([
            'montant_a_payer' => Money::fcfa($amount),
            'erreur' => $promoError !== null ? ['cle' => 'promo_code', 'texte' => $promoError] : null,
            'difficulte' => $promoError !== null ? 'promo_refuse' : null,
        ]));

        return view('site.checkout.show', [
            'formation' => $formation,
            'alreadyEnrolled' => $user?->isEnrolledIn($formation) ?? false,
            'unverifiedPurchase' => $unverified,
            'countries' => PaymentMethods::countries(),
            'methods' => PaymentMethods::enabled()->map(fn (PaymentMethod $method): array => $method->toArray())->values()->all(),
            // Seuil appliqué au montant réellement payé (remise comprise).
            'needsVerification' => $amount >= PaymentMethods::verificationThreshold(),
            'quote' => $quote,
            'promoCode' => $code,
            'promoError' => $promoError,
            'payAmount' => Money::fcfa($amount),
            'amountValue' => $amount,
        ]);
    }

    public function pay(StorePaymentProofRequest $request, Formation $formation, SubmitManualPayment $submit): RedirectResponse
    {
        // Un groupe seul (Groupe VIP) ne se vend jamais : il s'obtient avec une offre d'accompagnement.
        abort_unless($formation->is_active && ! $formation->isGroupOnly(), 404);

        if ($formation->isFree()) {
            return redirect(route('formations.show', $formation).'#inscription');
        }

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $proof */
        $proof = $request->file('proof');

        $purchase = $submit->handle($user, $formation, [
            'payment_method' => $request->string('payment_method')->toString(),
            'promo_code' => $request->string('promo_code')->toString(),
        ], $proof);

        return to_route('student.purchases.show', $purchase)->with('status', $purchase->status === PurchaseStatus::Provisional
            ? 'Merci ! Votre capture de paiement est bien reçue et votre formation est ouverte.'
            : 'Merci ! Votre capture de paiement est bien reçue.');
    }
}
