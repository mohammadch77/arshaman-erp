<?php

namespace App\Modules\Process\Enums;

enum StepType: string
{
    case Start = 'start';
    case Approval = 'approval';
    case Condition = 'condition';
    // فرستنده‌ی اصلی instance فرم را تکمیل می‌کند — بدون assignment_type/
    // assigned_role/assigned_user_id (همیشه started_by_user_id اقدام می‌کند)،
    // فقط یک مسیر خروجی (on_result='default').
    case RequesterInput = 'requester_input';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'شروع',
            self::Approval => 'تأیید',
            self::Condition => 'شرط',
            self::RequesterInput => 'تکمیل اطلاعات توسط درخواست‌دهنده',
            self::End => 'پایان',
        };
    }
}
