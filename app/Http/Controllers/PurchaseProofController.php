<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Payments\ProofImageProcessor;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Capture d'une preuve de paiement, lue sur le disque privé. Accès vérifié par PurchasePolicy::view
 * (élève propriétaire ou admin), jamais mise en cache par le navigateur ou un proxy.
 */
class PurchaseProofController extends Controller
{
    public function __invoke(Purchase $purchase): StreamedResponse
    {
        $disk = Storage::disk(ProofImageProcessor::DISK);

        abort_unless($purchase->proof_path && $disk->exists($purchase->proof_path), 404);

        return $disk->response($purchase->proof_path, 'preuve-'.$purchase->id.'.jpg', [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
