<?php

namespace App\Modules\Process\Enums;

enum ConditionOperator: string
{
    case GreaterThan = '>';
    case LessThan = '<';
    case Equal = '=';
    case GreaterThanOrEqual = '>=';
    case LessThanOrEqual = '<=';
    case NotEqual = '!=';

    public function label(): string
    {
        return match ($this) {
            self::GreaterThan => 'بزرگ‌تر از',
            self::LessThan => 'کوچک‌تر از',
            self::Equal => 'برابر با',
            self::GreaterThanOrEqual => 'بزرگ‌تر یا مساوی',
            self::LessThanOrEqual => 'کوچک‌تر یا مساوی',
            self::NotEqual => 'نامساوی با',
        };
    }
}
