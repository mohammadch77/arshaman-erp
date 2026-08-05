<?php

namespace App\Modules\Blog\Enums;

enum BlogPostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Scheduled => 'زمان‌بندی‌شده',
            self::Published => 'منتشرشده',
        };
    }
}
