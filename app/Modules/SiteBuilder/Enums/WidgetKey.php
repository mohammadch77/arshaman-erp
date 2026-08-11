<?php

namespace App\Modules\SiteBuilder\Enums;

enum WidgetKey: string
{
    case Container = 'container';
    case Title = 'title';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::Container => 'محفظه',
            self::Title => 'عنوان',
            self::Image => 'تصویر',
        };
    }
}
