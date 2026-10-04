<?php

namespace App\Enums;

/**
 * Les six réactions possibles sous un message de groupe, dans l'ordre affiché.
 */
enum ChatReaction: string
{
    case Like = 'like';
    case Love = 'love';
    case Laugh = 'laugh';
    case Wow = 'wow';
    case Pray = 'pray';
    case Clap = 'clap';

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Laugh => '😂',
            self::Wow => '😮',
            self::Pray => '🙏',
            self::Clap => '👏',
        };
    }

    /**
     * Nom lu par les lecteurs d'écran.
     */
    public function label(): string
    {
        return match ($this) {
            self::Like => 'J\'aime',
            self::Love => 'J\'adore',
            self::Laugh => 'Drôle',
            self::Wow => 'Étonnant',
            self::Pray => 'Merci',
            self::Clap => 'Bravo',
        };
    }
}
