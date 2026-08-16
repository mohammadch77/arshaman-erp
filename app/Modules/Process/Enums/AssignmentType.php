<?php

namespace App\Modules\Process\Enums;

enum AssignmentType: string
{
    case Role = 'role';
    case SpecificUser = 'specific_user';

    public function label(): string
    {
        return match ($this) {
            self::Role => 'نقش',
            self::SpecificUser => 'کاربر مشخص',
        };
    }
}
