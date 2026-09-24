<?php

namespace App\Events;

use App\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un élève a envoyé une preuve de paiement (statut « accès provisoire » ou « en attente de validation »).
 */
class PaymentSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Purchase $purchase) {}
}
