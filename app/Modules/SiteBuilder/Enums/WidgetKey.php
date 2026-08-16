<?php

namespace App\Modules\SiteBuilder\Enums;

enum WidgetKey: string
{
    case Container = 'container';
    case Title = 'title';
    case Image = 'image';
    case Button = 'button';
    case Gallery = 'gallery';
    case Testimonial = 'testimonial';
    case PricingTable = 'pricing_table';
    case FaqAccordion = 'faq_accordion';
    case Map = 'map';
    case Video = 'video';
    case Spacer = 'spacer';
    case HeaderNav = 'header_nav';
    case Footer = 'footer';
    case ContactForm = 'contact_form';
    case BlogPostList = 'blog_post_list';
    case TextEditor = 'text_editor';
    case Slider = 'slider';
    case CustomerSignupForm = 'customer_signup_form';

    public function label(): string
    {
        return match ($this) {
            self::Container => 'محفظه',
            self::Title => 'عنوان',
            self::Image => 'تصویر',
            self::Button => 'دکمه',
            self::Gallery => 'گالری',
            self::Testimonial => 'نظر مشتری',
            self::PricingTable => 'جدول قیمت‌گذاری',
            self::FaqAccordion => 'سوالات متداول',
            self::Map => 'نقشه',
            self::Video => 'ویدیو',
            self::Spacer => 'فاصله‌گذار',
            self::HeaderNav => 'منوی ناوبری',
            self::Footer => 'فوتر',
            self::ContactForm => 'فرم تماس',
            self::BlogPostList => 'فهرست پست‌های وبلاگ',
            self::TextEditor => 'متن غنی',
            self::Slider => 'اسلایدر تصاویر',
            self::CustomerSignupForm => 'فرم ثبت‌نام مشتری',
        };
    }
}
