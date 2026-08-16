<?php

namespace App\Modules\Process\Enums;

enum LogAction: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ConditionEvaluated = 'condition_evaluated';
    case Started = 'started';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'تأیید شد',
            self::Rejected => 'رد شد',
            self::ConditionEvaluated => 'شرط ارزیابی شد',
            self::Started => 'شروع شد',
            self::Completed => 'تکمیل شد',
        };
    }
}
