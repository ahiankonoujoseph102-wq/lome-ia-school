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

    // Accès donné par l'admin pour un paiement reçu hors du site (espèces, WhatsApp…) : aucune commission d'affiliation.
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::ManualMobileMoney => 'Mobile Money (preuve de paiement)',
            self::Manual => 'Accès manuel (paiement reçu hors du site)',
        };
    }

    /**
     * Moyens proposés pour un accès manuel : clé enregistrée dans « payment_method » => libellé.
     *
     * @return array<string, string>
     */
    public static function manualMethods(): array
    {
        return [
            'manual-cash' => 'Espèces',
            'manual-whatsapp' => 'Paiement convenu sur WhatsApp',
            'manual-other' => 'Autre',
        ];
    }
}
