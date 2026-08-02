<?php

namespace App\Modules\Core\Enums;

enum PartyType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'حقیقی',
            self::Company => 'حقوقی',
        };
    }
}
