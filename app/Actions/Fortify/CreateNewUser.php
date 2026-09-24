<?php

namespace App\Actions\Fortify;

use App\Affiliates\ReferralTracker;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input = $this->withNormalizedPhone($input);

        $validated = Validator::make($input, [
            ...$this->profileRules(),
            // Pas de confirmation du mot de passe : le champ propose « Afficher » pour vérifier la saisie.
            'password' => ['required', 'string', Password::default()],
            'wants_newsletter' => ['nullable', 'boolean'],
        ], [
            'phone.unique' => 'Ce numéro WhatsApp est déjà utilisé par un autre compte.',
            'email.unique' => 'Un compte existe déjà avec cette adresse e-mail. Connectez-vous.',
        ])->validate();

        $user = User::create([
            'name' => trim((string) $validated['name']),
            'email' => strtolower((string) $validated['email']),
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'wants_newsletter' => (bool) ($validated['wants_newsletter'] ?? false),
        ]);

        // Inscription arrivée par un lien d'affiliation (statistique « inscriptions » de l'affilié).
        $referrer = app(ReferralTracker::class)->current(request());

        if ($referrer !== null) {
            $user->forceFill(['referred_by_affiliate_id' => $referrer->id])->save();
        }

        return $user;
    }
}
