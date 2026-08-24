<?php

namespace App\Modules\Sales\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case DeliveredInstant = 'delivered_instant';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'دریافت‌شده',
            self::Paid => 'پرداخت‌شده',
            self::Preparing => 'در حال آماده‌سازی',
            self::Shipped => 'ارسال‌شده',
            self::Delivered => 'تحویل‌شده',
            self::DeliveredInstant => 'تحویل آنی',
            self::Closed => 'بسته‌شده',
            self::Cancelled => 'لغوشده',
            self::Returned => 'مرجوعی',
        };
    }
}
