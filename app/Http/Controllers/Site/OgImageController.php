<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Services\OgImageGenerator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Images d'aperçu (Open Graph) affichées lors d'un partage sur WhatsApp ou Facebook.
 */
class OgImageController extends Controller
{
    public function __construct(private OgImageGenerator $generator) {}

    public function site(): BinaryFileResponse
    {
        // Image de partage envoyée depuis l'admin (Réglages du site), sinon image générée.
        $uploaded = config('site.brand.og_image');

        if (is_string($uploaded) && $uploaded !== '' && Storage::disk('public')->exists($uploaded)) {
            return response()->file(Storage::disk('public')->path($uploaded), [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        return $this->respond($this->generator->forSite());
    }

    public function formation(Formation $formation): BinaryFileResponse
    {
        abort_unless($formation->is_active, 404);

        return $this->respond($this->generator->forFormation($formation));
    }

    private function respond(string $path): BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
