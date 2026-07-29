<?php

namespace App\Modules\HR\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Approved => 'تأییدشده',
            self::Rejected => 'ردشده',
        };
    }
}
