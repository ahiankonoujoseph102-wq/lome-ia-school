<?php

namespace App\Http\Controllers\Student;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * Sécurité du compte : mot de passe, double authentification et passkeys.
 * La double authentification et les passkeys passent par les routes de Fortify et de Laravel Passkeys.
 */
class SecurityController extends Controller
{
    use PasswordValidationRules;

    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        // Activation commencée mais pas encore confirmée par un code : l'élève peut la reprendre ou l'annuler.
        // Tant qu'elle n'est pas confirmée, aucun code n'est demandé à la connexion.
        $settingUpTwoFactor = $canManageTwoFactor
            && $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at === null
            && Fortify::confirmsTwoFactorAuthentication();

        return view('site.student.security', [
            'user' => $user,
            'canManageTwoFactor' => $canManageTwoFactor,
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'settingUpTwoFactor' => $settingUpTwoFactor,
            'qrCodeSvg' => $settingUpTwoFactor ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $settingUpTwoFactor ? Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret) : null,
            'recoveryCodes' => $user->hasEnabledTwoFactorAuthentication() ? $user->recoveryCodes() : [],
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys() ? $user->passkeys()->latest()->get() : collect(),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => $this->currentPasswordRules(),
            'password' => ['required', 'string', Password::default()],
        ]);

        $user->update(['password' => $validated['password']]);

        return to_route('security.edit')->with('status', 'password-updated');
    }
}
