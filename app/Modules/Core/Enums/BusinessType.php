<?php

namespace App\Modules\Core\Enums;

enum BusinessType: string
{
    case PhysicalGoods = 'physical_goods';
    case DigitalProduct = 'digital_product';
    case Hybrid = 'hybrid';
    case ProjectServices = 'project_services';
    case SharedOverhead = 'shared_overhead';

    public function label(): string
    {
        return match ($this) {
            self::PhysicalGoods => 'کالای فیزیکی',
            self::DigitalProduct => 'محصول دیجیتال',
            self::Hybrid => 'ترکیبی',
            self::ProjectServices => 'خدمات پروژه‌ای',
            self::SharedOverhead => 'ستاد مشترک',
        };
    }
}
