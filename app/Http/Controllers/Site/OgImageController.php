<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Services\OgImageGenerator;
use App\Support\Brand;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Images d'aperçu (Open Graph) affichées lors d'un partage sur WhatsApp ou Facebook.
 */
class OgImageController extends Controller
{
    public function __construct(private OgImageGenerator $generator) {}

    public function site(): Response
    {
        // Image de partage : logo vertical (valeur de départ) ou image envoyée depuis l'admin ; sinon image générée.
        $shared = Brand::path(config('site.brand.og_image'));

        if ($shared !== null) {
            return response()->file($shared, [
                'Content-Type' => Brand::mimeType($shared),
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        // Image envoyée sur un stockage objet (Laravel Cloud) : redirection vers son adresse publique.
        $url = Brand::url(config('site.brand.og_image'));

        if ($url !== null) {
            return redirect()->away($url)->setPublic()->setMaxAge(3600);
        }

        return $this->respond($this->generator->forSite());
    }

    /**
     * Image de partage de la page de Joseph Ahiankonou : nom, métier et photo (si elle existe).
     */
    public function founder(): BinaryFileResponse
    {
        return $this->respond($this->generator->forFounder(), 'image/jpeg');
    }

    public function formation(Formation $formation): BinaryFileResponse
    {
        abort_unless($formation->is_active, 404);

        return $this->respond($this->generator->forFormation($formation));
    }

    /**
     * Image de la formation recadrée en 1200 × 630 ; sans image lisible, retour au visuel généré.
     */
    public function formationPhoto(Formation $formation): Response
    {
        abort_unless($formation->is_active, 404);

        $path = $this->generator->forFormationPhoto($formation);

        return $path === null
            ? redirect()->route('og.formation', $formation)
            : $this->respond($path, 'image/jpeg');
    }

    private function respond(string $path, string $type = 'image/png'): BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
