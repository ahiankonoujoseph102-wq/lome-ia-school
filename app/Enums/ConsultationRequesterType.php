<?php

namespace App\Enums;

/**
 * Qui réserve la consultation gratuite : un particulier, un centre de formation ou une entreprise.
 * Hors particulier, le nom de la structure est obligatoire (consultations.organization_name).
 */
enum ConsultationRequesterType: string
{
    case Individual = 'individual';
    case TrainingCenter = 'training_center';
    case Company = 'company';

    /**
     * Libellé du formulaire (« Vous êtes : … »).
     */
    public function choiceLabel(): string
    {
        return match ($this) {
            self::Individual => 'Un particulier',
            self::TrainingCenter => 'Un centre de formation',
            self::Company => 'Une entreprise',
        };
    }

    /**
     * Libellé court (admin, e-mail, export).
     */
    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particulier',
            self::TrainingCenter => 'Centre de formation',
            self::Company => 'Entreprise',
        };
    }

    public function isOrganization(): bool
    {
        return $this !== self::Individual;
    }

    /**
     * Valeur présélectionnée par l'adresse (?profil=entreprise depuis la page Entreprises).
     */
    public static function fromQuery(?string $value): ?self
    {
        return match ($value) {
            'particulier' => self::Individual,
            'centre' => self::TrainingCenter,
            'entreprise' => self::Company,
            default => null,
        };
    }
}
