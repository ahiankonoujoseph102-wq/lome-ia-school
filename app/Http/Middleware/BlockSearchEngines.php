<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * NOINDEX=true : aucune réponse du site (pages, images, fichiers) n'est référencée par les moteurs
 * de recherche, par exemple tant que le site est sur une adresse provisoire.
 */
class BlockSearchEngines
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('app.noindex')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
