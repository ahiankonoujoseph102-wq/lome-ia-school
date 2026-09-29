<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paiements (formation ou supplément de session) et demandes de retrait d'affiliation : réservés aux comptes
 * dont l'adresse e-mail est confirmée. Sinon, direction la page « Confirmez votre e-mail », puis retour à la
 * page d'où venait l'élève une fois l'adresse confirmée.
 */
class EnsureEmailIsConfirmed
{
    public const string MESSAGE = 'Confirmez d\'abord votre adresse e-mail : c\'est nécessaire pour payer ou demander un retrait.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasConfirmedEmail()) {
            if ($request->expectsJson()) {
                abort(403, self::MESSAGE);
            }

            $request->session()->put('url.intended', url()->previous());

            return redirect()->route('email-confirmation.show')->with('status', self::MESSAGE);
        }

        return $next($request);
    }
}
