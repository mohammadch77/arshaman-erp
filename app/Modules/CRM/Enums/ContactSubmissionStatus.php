<?php

namespace App\Modules\CRM\Enums;

enum ContactSubmissionStatus: string
{
    case New = 'new';
    case Read = 'read';
    case InProgress = 'in_progress';
    case Replied = 'replied';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Read => 'خوانده‌شده',
            self::InProgress => 'در حال پیگیری',
            self::Replied => 'پاسخ‌داده‌شده',
            self::Archived => 'بایگانی‌شده',
        };
    }
}
