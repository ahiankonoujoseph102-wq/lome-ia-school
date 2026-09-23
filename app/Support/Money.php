<?php

namespace App\Support;

class Money
{
    /**
     * Formate un montant en francs CFA : 25000 devient « 25 000 FCFA ».
     * Les espaces sont insécables pour éviter un retour à la ligne au milieu du prix.
     */
    public static function fcfa(int $amount): string
    {
        return number_format($amount, 0, ',', "\u{00A0}")."\u{00A0}FCFA";
    }
}
