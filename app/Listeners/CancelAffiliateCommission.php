<?php

namespace App\Listeners;

use App\Affiliates\CommissionManager;
use App\Events\PaymentRefunded;
use App\Events\PaymentRejected;

/**
 * Achat refusé ou remboursé : commission annulée, ou déduite si elle a déjà été versée.
 */
class CancelAffiliateCommission
{
    public function __construct(private CommissionManager $commissions) {}

    public function handle(PaymentRejected|PaymentRefunded $event): void
    {
        $this->commissions->cancelFor($event->purchase, $event instanceof PaymentRefunded ? 'purchase_refunded' : 'purchase_rejected');
    }
}
