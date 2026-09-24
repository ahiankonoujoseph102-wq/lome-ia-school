<?php

namespace App\Http\Middleware;

use App\Affiliates\ReferralTracker;
use App\Models\Formation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lien d'affiliation (?ref=CODE) : mémorise le clic puis redirige vers la même page sans le code,
 * pour que l'adresse partagée ensuite par le visiteur ne transporte pas le code d'un autre.
 */
class TrackAffiliateReferral
{
    public function __construct(private ReferralTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->query->has('ref') || $request->expectsJson()) {
            return $next($request);
        }

        $formation = $request->route('formation');
        $cookies = $this->tracker->track($request, $formation instanceof Formation ? $formation : null);

        return redirect()->to($request->fullUrlWithoutQuery(['ref']))->withCookies($cookies);
    }
}
