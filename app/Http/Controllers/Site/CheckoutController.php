<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Récapitulatif de commande. Le paiement FedaPay y sera branché à l'étape 4.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, Formation $formation): View
    {
        abort_unless($formation->is_active, 404);

        $user = $request->user();

        // Un visiteur qui crée son compte ou se connecte depuis cette page y revient ensuite automatiquement.
        if (! $user) {
            $request->session()->put('url.intended', route('checkout.show', $formation));
        }

        return view('site.checkout.show', [
            'formation' => $formation,
            'alreadyEnrolled' => $user?->isEnrolledIn($formation) ?? false,
        ]);
    }
}
