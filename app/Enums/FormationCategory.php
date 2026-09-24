<?php

namespace App\Enums;

enum FormationCategory: string
{
    case AiBasics = 'ai-basics';
    case Creative = 'creative';
    case Web = 'web';
    case Automation = 'automation';
    case Business = 'business';
    case Writing = 'writing';

    public function label(): string
    {
        return match ($this) {
            self::AiBasics => 'Initiation à l\'IA',
            self::Creative => 'Création visuelle, vidéo et musique',
            self::Web => 'Web, applications et SaaS',
            self::Automation => 'Automatisation et agents IA',
            self::Business => 'Business en ligne',
            self::Writing => 'Écriture et édition',
        };
    }

    /**
     * Libellé court pour les filtres sur mobile.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::AiBasics => 'Initiation',
            self::Creative => 'Création',
            self::Web => 'Web & apps',
            self::Automation => 'Automatisation',
            self::Business => 'Business',
            self::Writing => 'Écriture',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AiBasics => 'sparkles',
            self::Creative => 'paint-brush',
            self::Web => 'code-bracket',
            self::Automation => 'cpu-chip',
            self::Business => 'briefcase',
            self::Writing => 'pencil-square',
        };
    }

    /**
     * Couleurs du dégradé des visuels de remplacement : [début, fin].
     *
     * @return array{0: string, 1: string}
     */
    public function gradient(): array
    {
        return match ($this) {
            self::AiBasics => ['#4F46E5', '#8B5CF6'],
            self::Creative => ['#7C3AED', '#DB2777'],
            self::Web => ['#2563EB', '#7C3AED'],
            self::Automation => ['#0EA5E9', '#6D28D9'],
            self::Business => ['#6D28D9', '#D97706'],
            self::Writing => ['#312E81', '#A855F7'],
        };
    }
}
