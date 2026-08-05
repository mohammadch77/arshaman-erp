<?php

namespace App\Modules\CRM\Enums;

enum ContactAttemptOutcome: string
{
    case AnsweredResolved = 'answered_resolved';
    case AnsweredFollowupNeeded = 'answered_followup_needed';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case WrongNumber = 'wrong_number';
    case WillCallBack = 'will_call_back';

    public function label(): string
    {
        return match ($this) {
            self::AnsweredResolved => 'پاسخ داد، مشکل حل شد',
            self::AnsweredFollowupNeeded => 'پاسخ داد، نیاز به پیگیری',
            self::NoAnswer => 'پاسخ نداد',
            self::Busy => 'اشغال بود',
            self::WrongNumber => 'شماره اشتباه',
            self::WillCallBack => 'خودش تماس می‌گیرد',
        };
    }
}
