<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro WhatsApp déjà normalisé au format international (voir App\Support\PhoneNumber).
 */
class WhatsAppNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\+[1-9]\d{7,14}$/', $value)) {
            $fail('Le numéro WhatsApp n\'est pas valide. Exemple : 90 12 34 56.');
        }
    }
}
