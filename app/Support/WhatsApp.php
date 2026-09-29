<?php

namespace App\Support;

class WhatsApp
{
    /**
     * Lien wa.me qui ouvre une conversation avec un message prérempli.
     */
    public static function url(?string $message = null): string
    {
        $number = (string) config('site.whatsapp.number');
        $message ??= (string) config('site.whatsapp.message');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    /**
     * Lien tel: à partir d'un numéro affiché, ex. « +228 72 24 02 31 ».
     */
    public static function tel(string $phone): string
    {
        return 'tel:'.preg_replace('/[^\d+]/', '', $phone);
    }
}
