<?php

namespace App\Modules\SiteBuilder\Enums;

enum PageCategoryKey: string
{
    case Home = 'home';
    case About = 'about';
    case Contact = 'contact';
    case Services = 'services';
    case Blog = 'blog';
    case Login = 'login';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'خانه',
            self::About => 'درباره ما',
            self::Contact => 'تماس با ما',
            self::Services => 'خدمات',
            self::Blog => 'وبلاگ',
            self::Login => 'ورود',
        };
    }
}
