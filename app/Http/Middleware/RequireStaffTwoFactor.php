<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Double authentification obligatoire pour l'admin et les modérateurs : tant qu'elle n'est pas activée
 * (et confirmée par un code), ils sont envoyés vers l'écran de mise en place de « Sécurité du compte ».
 */
class RequireStaffTwoFactor
{
    public const string MESSAGE = 'La double authentification est obligatoire pour l\'administration et la modération. Activez-la pour continuer.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isStaff() && ! $user->hasEnabledTwoFactorAuthentication()) {
            if ($request->expectsJson()) {
                abort(403, self::MESSAGE);
            }

            return redirect()->route('security.edit')->with('two_factor_required', true);
        }

        return $next($request);
    }
}
