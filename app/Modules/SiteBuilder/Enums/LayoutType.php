<?php

namespace App\Modules\SiteBuilder\Enums;

enum LayoutType: string
{
    case Header = 'header';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'هدر',
            self::Footer => 'فوتر',
        };
    }
}
