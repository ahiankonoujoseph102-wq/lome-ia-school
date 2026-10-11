<?php

namespace App\Contracts;

use App\WhatsApp\WhatsAppSendFailed;

/**
 * Envoi de messages WhatsApp (agent WhatsApp). Implémentation : App\WhatsApp\CloudApiSender (API WhatsApp Business
 * Cloud de Meta), appelée uniquement par le serveur. Interface pour pouvoir changer de fournisseur (partenaire de
 * Meta, autre API) sans toucher au reste du code.
 */
interface WhatsAppSender
{
    /**
     * Jeton et identifiant du numéro présents ?
     */
    public function isConfigured(): bool;

    /**
     * Envoie un message texte et renvoie l'identifiant du message chez Meta (« wamid… »).
     *
     * @param  string  $to  numéro du client, chiffres seuls avec l'indicatif (22890123456)
     *
     * @throws WhatsAppSendFailed
     */
    public function sendText(string $to, string $text): string;

    /**
     * Marque un message reçu comme lu (coches bleues) et affiche « en train d'écrire… » au client.
     * Sans conséquence en cas d'échec.
     */
    public function markAsRead(string $messageId): void;
}
