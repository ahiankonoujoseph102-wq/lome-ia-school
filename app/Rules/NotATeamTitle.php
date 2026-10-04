<?php

namespace App\Rules;

use App\Chat\ChatBadges;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un nom de compte ne peut pas contenir un titre de l'équipe (« Formateur principal », « Administrateur »,
 * « Modérateur », « Équipe »…) : personne ne peut imiter les étiquettes des groupes avec son nom.
 */
class NotATeamTitle implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ChatBadges::imitates($value)) {
            $fail('Ce nom ne peut pas contenir « Formateur », « Administrateur », « Modérateur » ni « Équipe » : ces titres sont réservés à l\'équipe de Lomé IA School.');
        }
    }
}
