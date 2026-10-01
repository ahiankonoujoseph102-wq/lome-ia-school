<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Site installable sur l'écran d'accueil : manifeste et service worker.
 *
 * Le service worker ne met en cache que les fichiers publics et immuables (CSS/JS compilés, icônes,
 * police) : jamais une page, un message, un fichier de groupe ni une réponse de l'espace membre.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'short_name' => 'Lomé IA',
            'description' => 'Formations en intelligence artificielle, cours et groupes de discussion.',
            'lang' => 'fr',
            'dir' => 'ltr',
            // Identité de l'application : l'ancienne adresse de départ, pour que les applications déjà installées
            // reçoivent ce manifeste comme une mise à jour (sans « id », Chrome prend l'adresse de départ).
            'id' => '/mon-espace?source=app',
            // Ouverture depuis l'icône : Mon espace pour un élève connecté, l'accueil pour un visiteur (start()).
            'start_url' => '/application',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#030a2c',
            'theme_color' => '#0b1030',
            'icons' => [
                ['src' => Brand::icon('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => Brand::icon('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => Brand::icon('icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => Brand::icon('icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Mes groupes', 'url' => '/mon-espace/groupes', 'icons' => [['src' => Brand::icon('icon-192.png'), 'sizes' => '192x192']]],
                ['name' => 'Mes formations', 'url' => '/mon-espace/formations', 'icons' => [['src' => Brand::icon('icon-192.png'), 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Adresse de départ de l'application installée.
     */
    public function start(Request $request): RedirectResponse
    {
        return redirect()->route($request->user() ? 'dashboard' : 'home');
    }

    public function serviceWorker(): Response
    {
        return response()
            ->view('pwa.service-worker', ['version' => $this->version()])
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Service-Worker-Allowed', '/');
    }

    /**
     * Change à chaque compilation des fichiers CSS/JS : l'ancien cache est alors vidé.
     */
    private function version(): string
    {
        $manifest = public_path('build/manifest.json');

        return is_file($manifest) ? substr((string) md5_file($manifest), 0, 12) : 'dev';
    }
}
