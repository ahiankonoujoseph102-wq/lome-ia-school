<?php

namespace App\Assistant;

use RuntimeException;

/**
 * Refus ou échec de Google pour la voix naturelle (quota, erreur, délai dépassé, réponse sans audio). Le message ne
 * contient jamais la clé d'API. Ama parle alors avec la voix du téléphone.
 */
class SpeechFailure extends RuntimeException
{
    /**
     * @param  bool  $quota  quota atteint (réponse 429 de Google)
     * @param  bool  $daily  quota du jour épuisé : plus aucun essai jusqu'à la nouvelle journée de Google
     */
    public function __construct(string $detail = '', public readonly bool $quota = false, public readonly bool $daily = false)
    {
        parent::__construct($detail);
    }
}
