<?php

namespace App\Http\Middleware;

use App\Support\SearchEngines;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tête X-Robots-Tag « noindex » sur toute réponse qui ne doit pas être référencée (voir SearchEngines) :
 * pages privées et pages avec un jeton ou un paramètre, toujours ; tout le site avec NOINDEX=true.
 */
class BlockSearchEngines
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (($directive = SearchEngines::directive($request)) !== null) {
            $response->headers->set('X-Robots-Tag', $directive);
        }

        return $response;
    }
}
