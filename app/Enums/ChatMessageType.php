<?php

namespace App\Enums;

enum ChatMessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Voice = 'voice';
    case Video = 'video';
    case File = 'file';

    // Lieu partagé (interrupteur « Partage de localisation ») : carte avec lien Google Maps, sans fichier.
    case Location = 'location';

    // Sondage (interrupteur « Sondages ») : question et choix dans les tables chat_poll*.
    case Poll = 'poll';

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
            self::Location => '📍 Lieu',
            self::Poll => '📊 Sondage',
        };
    }

    public function hasAttachment(): bool
    {
        return in_array($this, self::uploadable(), true);
    }

    /**
     * Types envoyés par la zone d'écriture habituelle (les autres ont leur propre formulaire).
     *
     * @return list<self>
     */
    public static function postable(): array
    {
        return [self::Text, ...self::uploadable()];
    }

    /**
     * @return list<self>
     */
    public static function uploadable(): array
    {
        return [self::Image, self::Voice, self::Video, self::File];
    }
}
