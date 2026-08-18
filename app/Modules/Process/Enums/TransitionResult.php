<?php

namespace App\Modules\Process\Enums;

enum TransitionResult: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ConditionTrue = 'condition_true';
    case ConditionFalse = 'condition_false';
    // تنها نتیجه‌ی ممکن مرحله‌ی requester_input — بدون شاخه‌ی دوگانه.
    case Default = 'default';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'تأیید شد',
            self::Rejected => 'رد شد',
            self::ConditionTrue => 'شرط برقرار بود',
            self::ConditionFalse => 'شرط برقرار نبود',
            self::Default => 'ارسال شد',
        };
    }
}
