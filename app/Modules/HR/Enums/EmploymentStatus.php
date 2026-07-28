<?php

namespace App\Modules\HR\Enums;

enum EmploymentStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::OnLeave => 'مرخصی',
            self::Terminated => 'پایان‌یافته',
        };
    }
}
