<?php

namespace App\Modules\Process\Enums;

enum ProcessStatus: string
{
    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'در جریان',
            self::Approved => 'تأیید نهایی',
            self::Rejected => 'رد نهایی',
            self::Cancelled => 'لغوشده',
        };
    }
}
