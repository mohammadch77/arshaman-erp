<?php

namespace App\Modules\HR\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Calculated => 'محاسبه‌شده',
            self::Finalized => 'نهایی‌شده',
        };
    }

    /**
     * قفل مالی — بعد از نهایی‌شدن، هیچ فیش این دوره قابل ویرایش یا بازمحاسبه نیست.
     * نگاه کن: CLAUDE.md بند ۵.۵ (سند posted غیرقابل ویرایش است).
     */
    public function isLocked(): bool
    {
        return $this === self::Finalized;
    }
}
