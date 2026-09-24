<?php

namespace App\Events;

use App\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Paiement confirmé par l'équipe (ou, plus tard, par FedaPay).
 * Étape 8 : la commission d'affiliation se calcule UNIQUEMENT ici, jamais sur un accès provisoire.
 */
class PaymentValidated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Purchase $purchase) {}
}
