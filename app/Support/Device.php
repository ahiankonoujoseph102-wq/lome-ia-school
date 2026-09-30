<?php

namespace App\Support;

/**
 * Reconnaissance simple de l'appareil du visiteur, à partir de l'en-tête User-Agent.
 */
class Device
{
    /**
     * iPhone, iPad ou iPod : les liens « tel: » contenant * ou # n'y fonctionnent pas (bouton USSD masqué).
     * Un iPad qui se présente comme un Mac est reconnu dans le navigateur (site.js, « canDial »).
     */
    public static function isIos(?string $userAgent): bool
    {
        return $userAgent !== null && preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1;
    }
}
