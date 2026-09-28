<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Payments\ProofImageProcessor;
use App\Support\FileStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture d'une preuve de paiement, lue sur le disque privé. Accès vérifié par PurchasePolicy::view
 * (élève propriétaire ou admin), jamais mise en cache par le navigateur ou un proxy.
 * Sur un stockage objet (Laravel Cloud) : lien temporaire signé, jamais public.
 */
class PurchaseProofController extends Controller
{
    public function __invoke(Purchase $purchase): Response
    {
        $path = (string) $purchase->proof_path;
        $filename = 'preuve-'.$purchase->id.'.jpg';

        return FileStorage::privateResponse(ProofImageProcessor::DISK, $path, $filename, true, 'image/jpeg', fn (Filesystem $disk): Response => $disk->response($path, $filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
