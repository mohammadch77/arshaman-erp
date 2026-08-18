<?php

namespace App\Modules\Process\Enums;

enum LogAction: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ConditionEvaluated = 'condition_evaluated';
    case Started = 'started';
    case Completed = 'completed';
    // یادآوری ادمین به مسئول مرحله‌ی فعلی — بدون تغییر current_step/status.
    case Reminder = 'reminder';
    // علامت‌گذاری یک تصمیم تأیید/رد قبلی به‌عنوان بازگردانی‌شده (بند ۴ Session جاری) —
    // رکورد لاگ اصلی هرگز حذف/ویرایش نمی‌شود، این یک ورودی جدید در تاریخچه است.
    case Reversed = 'reversed';
    // فرستنده‌ی اصلی instance فرم مرحله‌ی requester_input را ارسال کرد.
    case RequesterInput = 'requester_input';
    // فرستنده‌ی اصلی request_data فرایند آزاد را ویرایش کرد (بخش ۳ Session جاری) —
    // فقط قبل از این‌که مرحله‌ی فعلی هیچ اقدامی داشته باشد.
    case RequestUpdated = 'request_updated';
    // فرستنده‌ی اصلی کل instance را لغو کرد (بخش ۳ Session جاری).
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'تأیید شد',
            self::Rejected => 'رد شد',
            self::ConditionEvaluated => 'شرط ارزیابی شد',
            self::Started => 'شروع شد',
            self::Completed => 'تکمیل شد',
            self::Reminder => 'یادآوری ادمین',
            self::Reversed => 'تصمیم بازگردانی شد',
            self::RequesterInput => 'اطلاعات توسط درخواست‌دهنده تکمیل شد',
            self::RequestUpdated => 'درخواست توسط فرستنده ویرایش شد',
            self::Cancelled => 'توسط فرستنده لغو شد',
        };
    }
}
