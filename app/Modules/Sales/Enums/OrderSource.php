<?php

namespace App\Modules\Sales\Enums;

enum OrderSource: string
{
    case Woocommerce = 'woocommerce';
    case ManualInstagram = 'manual_instagram';
    case ManualTelegram = 'manual_telegram';
    case ManualOther = 'manual_other';

    public function label(): string
    {
        return match ($this) {
            self::Woocommerce => 'ووکامرس',
            self::ManualInstagram => 'دستی — اینستاگرام',
            self::ManualTelegram => 'دستی — تلگرام',
            self::ManualOther => 'دستی — سایر',
        };
    }

    public function isManual(): bool
    {
        return $this !== self::Woocommerce;
    }
}
