<?php

namespace App\Modules\HR\Enums;

enum RecordedBy: string
{
    case SelfService = 'self';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::SelfService => 'خودِ کارمند',
            self::Admin => 'ادمین',
        };
    }
}
