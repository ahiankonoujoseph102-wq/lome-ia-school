<?php

namespace App\Listeners;

use App\Affiliates\CommissionManager;
use App\Events\PaymentValidated;

/**
 * La commission d'affiliation est créée UNIQUEMENT à la validation du paiement par l'admin.
 */
class CreateAffiliateCommission
{
    public function __construct(private CommissionManager $commissions) {}

    public function handle(PaymentValidated $event): void
    {
        $this->commissions->createFor($event->purchase);
    }
}
