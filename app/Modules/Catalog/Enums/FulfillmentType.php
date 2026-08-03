<?php

namespace App\Modules\Catalog\Enums;

enum FulfillmentType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'کالای فیزیکی',
            self::Digital => 'محصول دیجیتال',
            self::Service => 'خدمات',
        };
    }
}
