<?php

namespace App\Modules\CRM\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'کم',
            self::Normal => 'عادی',
            self::High => 'زیاد',
        };
    }
}
