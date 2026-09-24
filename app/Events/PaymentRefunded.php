<?php

namespace App\Events;

use App\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Achat remboursé (formation ou supplément de session) : la commission d'affiliation est annulée,
 * ou déduite des prochaines commissions si elle a déjà été versée.
 */
class PaymentRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(public Purchase $purchase) {}
}
