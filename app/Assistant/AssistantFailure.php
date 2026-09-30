<?php

namespace App\Assistant;

use App\Enums\AssistantIncidentType;
use RuntimeException;

/**
 * Échec d'une demande à l'IA (erreur, quota, délai dépassé, réponse vide). Le message ne contient jamais la
 * clé d'API ; il est noté comme incident pour l'admin, et le visiteur voit un message clair avec WhatsApp.
 */
class AssistantFailure extends RuntimeException
{
    public function __construct(public readonly AssistantIncidentType $type, string $detail = '')
    {
        parent::__construct($detail);
    }
}
