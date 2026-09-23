<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\WhatsAppNumber;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'phone' => $this->phoneRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate WhatsApp numbers (already normalized).
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            new WhatsAppNumber,
            $userId === null
                ? Rule::unique(User::class, 'phone')
                : Rule::unique(User::class, 'phone')->ignore($userId),
        ];
    }

    /**
     * Remplace le numéro saisi par sa version internationale (+228…) avant la validation.
     * Un numéro invalide est laissé tel quel pour que la validation affiche l'erreur.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function withNormalizedPhone(array $input): array
    {
        $raw = is_string($input['phone'] ?? null) ? $input['phone'] : '';
        $dialCode = is_string($input['phone_country'] ?? null) ? $input['phone_country'] : null;

        $input['phone'] = PhoneNumber::normalize($raw, $dialCode) ?? $raw;

        return $input;
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
