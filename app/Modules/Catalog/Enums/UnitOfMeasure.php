<?php

namespace App\Modules\Catalog\Enums;

enum UnitOfMeasure: string
{
    case Piece = 'piece';
    case Kilogram = 'kilogram';
    case Liter = 'liter';
    case Meter = 'meter';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'عدد',
            self::Kilogram => 'کیلوگرم',
            self::Liter => 'لیتر',
            self::Meter => 'متر',
            self::Box => 'جعبه',
        };
    }
}
