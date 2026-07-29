<?php

namespace App\Modules\HR\Enums;

/**
 * وضعیت ثبت فیش حقوقی به‌عنوان هزینه در دفتر کل.
 *
 * TODO: اتصال به Finance/Expenses — نگاه کن BACKLOG.md #1
 * در فاز ۲ همیشه Pending می‌ماند؛ ماژول هزینه‌ها (فاز ۴) هنوز وجود ندارد.
 */
enum ExpensePostingStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار ثبت هزینه',
            self::Posted => 'ثبت‌شده در هزینه‌ها',
        };
    }
}
