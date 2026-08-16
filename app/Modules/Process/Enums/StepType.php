<?php

namespace App\Modules\Process\Enums;

enum StepType: string
{
    case Start = 'start';
    case Approval = 'approval';
    case Condition = 'condition';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'شروع',
            self::Approval => 'تأیید',
            self::Condition => 'شرط',
            self::End => 'پایان',
        };
    }
}
