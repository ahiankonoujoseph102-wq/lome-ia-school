<?php

namespace App\Http\Requests;

use App\Payments\PaymentMethods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire de preuve de paiement Mobile Money : le moyen choisi et la capture d'écran, rien d'autre.
 */
class StorePaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::enabled()->keys()->all())],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('site.payments.proof_max_kb', 8192)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Choisissez le moyen de paiement que vous avez utilisé.',
            'payment_method.in' => 'Choisissez un moyen de paiement dans la liste.',
            'proof.required' => 'Ajoutez la capture d\'écran de votre paiement.',
            'proof.image' => 'La preuve doit être une image (capture d\'écran).',
            'proof.mimes' => 'La capture doit être au format JPG, PNG ou WebP.',
            'proof.max' => 'La capture ne doit pas dépasser '.intdiv((int) config('site.payments.proof_max_kb', 8192), 1024).' Mo.',
            'proof.uploaded' => 'La capture n\'a pas pu être envoyée. Réessayez avec une image plus légère.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'proof' => 'capture d\'écran',
        ];
    }
}
