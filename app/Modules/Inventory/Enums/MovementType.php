<?php

namespace App\Modules\Inventory\Enums;

enum MovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::In => 'دریافت کالا',
            self::Out => 'خروج کالا',
            self::Adjust => 'اصلاح موجودی',
        };
    }
}
