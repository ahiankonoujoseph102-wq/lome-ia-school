<?php

namespace App\Enums;

enum ChatMessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Voice = 'voice';
    case Video = 'video';
    case File = 'file';

    /**
     * Aperçu court (liste des groupes, notifications, citation d'une réponse).
     */
    public function preview(): string
    {
        return match ($this) {
            self::Text => 'Message',
            self::Image => '📷 Photo',
            self::Voice => '🎤 Note vocale',
            self::Video => '🎬 Vidéo',
            self::File => '📄 Fichier',
        };
    }

    public function hasAttachment(): bool
    {
        return $this !== self::Text;
    }
}
