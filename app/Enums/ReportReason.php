<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Inappropriate = 'inappropriate';
    case Harassment = 'harassment';
    case Scam = 'scam';
    case PersonalData = 'personal_data';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Publicité ou messages répétés',
            self::Inappropriate => 'Contenu inapproprié',
            self::Harassment => 'Insulte ou harcèlement',
            self::Scam => 'Arnaque ou fausse information',
            self::PersonalData => 'Données personnelles partagées',
            self::Other => 'Autre raison',
        };
    }
}
