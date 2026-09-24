<?php

namespace App\Enums;

/**
 * Façon dont un achat a été payé. Aujourd'hui : Mobile Money manuel avec preuve de paiement.
 * Pour ajouter FedaPay plus tard : un nouveau cas ici, son propre parcours (redirection + webhook),
 * puis App\Services\PurchaseManager::validate() quand le paiement est confirmé.
 */
enum PaymentGateway: string
{
    case ManualMobileMoney = 'manual_mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::ManualMobileMoney => 'Mobile Money (preuve de paiement)',
        };
    }
}
