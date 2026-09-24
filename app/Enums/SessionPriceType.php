<?php

namespace App\Enums;

enum SessionPriceType: string
{
    case Included = 'included';
    case Supplement = 'supplement';

    public function label(): string
    {
        return match ($this) {
            self::Included => 'Inclus dans la formation',
            self::Supplement => 'Supplément',
        };
    }
}
