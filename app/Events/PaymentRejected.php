<?php

namespace App\Events;

use App\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Paiement refusé par l'équipe : l'accès éventuel a déjà été retiré (voir FormationAccessRevoked).
 */
class PaymentRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public Purchase $purchase) {}
}
