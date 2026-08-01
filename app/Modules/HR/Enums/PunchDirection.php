<?php

namespace App\Modules\HR\Enums;

enum PunchDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'ثبت ورود',
            self::Out => 'ثبت خروج',
        };
    }
}
