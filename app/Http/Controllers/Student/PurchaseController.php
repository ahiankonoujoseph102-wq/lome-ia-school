<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * « Mes achats » : suivi des paiements et reçus de l'élève.
 */
class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.student.purchases.index', [
            'purchases' => $user->purchases()->with('formation')->latest()->get(),
        ]);
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load('formation');

        return view('site.student.purchases.show', ['purchase' => $purchase]);
    }

    /**
     * Reçu imprimable d'un paiement validé.
     */
    public function receipt(Purchase $purchase): View
    {
        $purchase->load(['formation', 'user']);

        return view('site.student.purchases.receipt', ['purchase' => $purchase]);
    }
}
