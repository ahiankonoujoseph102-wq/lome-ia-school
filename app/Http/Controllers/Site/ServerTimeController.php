<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Heure du serveur (millisecondes depuis 1970), pour corriger l'horloge du téléphone dans les comptes à rebours
 * des offres d'accompagnement (resources/js/countdown.js, createServerClock). Jamais gardée en cache.
 */
class ServerTimeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['now' => now()->getTimestampMs()])
            ->header('Cache-Control', 'no-store, private, max-age=0');
    }
}
