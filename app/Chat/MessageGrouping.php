<?php

namespace App\Chat;

use App\Models\ChatMessage;

/**
 * Messages consécutifs d'une même personne, à moins de 5 minutes d'intervalle et le même jour :
 * le nom et la photo ne sont affichés qu'une fois, comme dans une messagerie.
 */
class MessageGrouping
{
    public static function continues(ChatMessage $message, ?ChatMessage $previous): bool
    {
        return $previous !== null
            && $previous->user_id === $message->user_id
            && $previous->created_at->isSameDay($message->created_at)
            && abs($message->created_at->diffInMinutes($previous->created_at)) < 5;
    }
}
