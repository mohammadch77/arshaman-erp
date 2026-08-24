<?php

namespace App\Modules\Shipping\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار بسته‌بندی',
            self::Packed => 'بسته‌بندی‌شده',
            self::Shipped => 'ارسال‌شده',
            self::Delivered => 'تحویل‌شده',
        };
    }
}
