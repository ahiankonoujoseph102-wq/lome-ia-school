<?php

namespace App\WhatsApp;

use RuntimeException;

/**
 * Échec d'un envoi WhatsApp (jeton absent ou refusé, fenêtre de 24 h fermée, numéro invalide, Meta injoignable).
 * Le message ne contient jamais le jeton ; il est enregistré sur le message pour l'admin.
 */
class WhatsAppSendFailed extends RuntimeException
{
    /**
     * Code d'erreur de Meta : la fenêtre de 24 h est fermée (le client doit réécrire, ou un modèle approuvé est nécessaire).
     */
    public const int WINDOW_CLOSED = 131047;

    public function __construct(string $detail, public readonly ?int $metaCode = null)
    {
        parent::__construct($detail);
    }
}
