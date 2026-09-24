<?php

namespace App\Http\Requests;

use App\Payments\PaymentMethods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire de preuve de paiement Mobile Money.
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
            'payer_phone' => ['required', 'string', 'max:30'],
            'payer_name' => ['required', 'string', 'min:2', 'max:120'],
            'transaction_id' => ['required', 'string', 'min:4', 'max:100', 'regex:/^[\pL\pN\s.\-_\/:#]+$/u'],
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
            'payer_phone.required' => 'Indiquez le numéro qui a effectué le paiement.',
            'payer_name.required' => 'Indiquez le nom du titulaire du compte qui a payé.',
            'transaction_id.required' => 'L\'identifiant de transaction est obligatoire : il figure dans le SMS de confirmation.',
            'transaction_id.regex' => 'L\'identifiant de transaction ne doit contenir que des lettres, des chiffres et les signes . - _ / : #',
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
            'payer_phone' => 'numéro qui a payé',
            'payer_name' => 'nom de l\'expéditeur',
            'transaction_id' => 'identifiant de transaction',
            'proof' => 'capture d\'écran',
        ];
    }
}
