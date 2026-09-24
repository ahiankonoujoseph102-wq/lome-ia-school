<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'À traiter',
            self::Resolved => 'Traité',
            self::Dismissed => 'Classé sans suite',
        };
    }
}
