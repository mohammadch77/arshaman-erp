<?php

namespace App\Modules\Inventory\Enums;

enum MovementType: string
{
    case PurchaseIn = 'purchase_in';
    case SaleOut = 'sale_out';
    case ReturnIn = 'return_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case WasteOut = 'waste_out';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'خرید (ورود)',
            self::SaleOut => 'فروش (خروج)',
            self::ReturnIn => 'مرجوعی (ورود)',
            self::AdjustmentIn => 'تعدیل افزایشی',
            self::AdjustmentOut => 'تعدیل کاهشی',
            self::WasteOut => 'ضایعات (خروج)',
            self::TransferOut => 'جابجایی (خروج از انبار مبدأ)',
            self::TransferIn => 'جابجایی (ورود به انبار مقصد)',
        };
    }

    /**
     * جهت اثر روی quantity_on_hand — Action ها بر همین اساس افزایش/کاهش می‌دهند.
     */
    public function direction(): string
    {
        return match ($this) {
            self::PurchaseIn, self::ReturnIn, self::AdjustmentIn, self::TransferIn => 'in',
            self::SaleOut, self::WasteOut, self::AdjustmentOut, self::TransferOut => 'out',
        };
    }
}
