<?php

namespace Database\Seeders;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * تکمیل کاتالوگ ویجت‌ها (Session گسترش) — ده ویجت جدید، اضافه به سه ویجت
 * پایه SiteBuilderSeeder (که دست‌نخورده می‌ماند). widgets سراسری‌اند
 * (بدون owner_company_id)، پس updateOrCreate امن است.
 */
class SiteBuilderWidgetsExpansionSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = [
            [
                'widget_key' => WidgetKey::Button->value,
                'name' => 'دکمه',
                'icon' => 'o-cursor-arrow-rays',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'label', 'type' => 'text', 'label' => 'متن دکمه'],
                        ['key' => 'url', 'type' => 'text', 'label' => 'لینک'],
                        [
                            'key' => 'style',
                            'type' => 'select',
                            'label' => 'سبک',
                            'options' => [
                                ['value' => 'primary', 'label' => 'اصلی'],
                                ['value' => 'outline', 'label' => 'خط‌دور'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Gallery->value,
                'name' => 'گالری',
                'icon' => 'o-photo',
                'default_config' => [
                    'editable_fields' => [
                        [
                            'key' => 'images',
                            'type' => 'repeater',
                            'label' => 'تصاویر گالری',
                            'item_fields' => [
                                ['key' => 'image_path', 'type' => 'image', 'label' => 'تصویر'],
                                ['key' => 'caption', 'type' => 'text', 'label' => 'عنوان (اختیاری)'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Testimonial->value,
                'name' => 'نظر مشتری',
                'icon' => 'o-chat-bubble-left-right',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'quote_text', 'type' => 'textarea', 'label' => 'متن نظر'],
                        ['key' => 'customer_name', 'type' => 'text', 'label' => 'نام مشتری'],
                        ['key' => 'customer_title', 'type' => 'text', 'label' => 'عنوان/شرکت مشتری'],
                        ['key' => 'customer_photo', 'type' => 'image', 'label' => 'عکس مشتری (اختیاری)'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::PricingTable->value,
                'name' => 'جدول قیمت‌گذاری',
                'icon' => 'o-tag',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'plan_name', 'type' => 'text', 'label' => 'نام پلن'],
                        ['key' => 'price', 'type' => 'text', 'label' => 'قیمت'],
                        ['key' => 'features', 'type' => 'lines', 'label' => 'فهرست ویژگی‌ها (هر خط یک ویژگی)'],
                        ['key' => 'cta_label', 'type' => 'text', 'label' => 'متن دکمه'],
                        ['key' => 'cta_url', 'type' => 'text', 'label' => 'لینک دکمه'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::FaqAccordion->value,
                'name' => 'سوالات متداول',
                'icon' => 'o-question-mark-circle',
                'default_config' => [
                    'editable_fields' => [
                        [
                            'key' => 'items',
                            'type' => 'repeater',
                            'label' => 'سوالات و جواب‌ها',
                            'item_fields' => [
                                ['key' => 'question', 'type' => 'text', 'label' => 'سوال'],
                                ['key' => 'answer', 'type' => 'textarea', 'label' => 'جواب'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Map->value,
                'name' => 'نقشه',
                'icon' => 'o-map-pin',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'embed_url', 'type' => 'text', 'label' => 'لینک embed نقشه گوگل (فقط google.com/maps/embed)'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Video->value,
                'name' => 'ویدیو',
                'icon' => 'o-play-circle',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'video_url', 'type' => 'text', 'label' => 'لینک ویدیو (فقط یوتیوب یا آپارات)'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Spacer->value,
                'name' => 'فاصله‌گذار',
                'icon' => 'o-arrows-up-down',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'height_px', 'type' => 'text', 'label' => 'ارتفاع (پیکسل)'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::HeaderNav->value,
                'name' => 'منوی ناوبری',
                'icon' => 'o-bars-3',
                'default_config' => [
                    'editable_fields' => [
                        [
                            'key' => 'nav_links',
                            'type' => 'repeater',
                            'label' => 'آیتم‌های منو',
                            // هر آیتم به یک دسته‌ی صفحه (نه یک URL آزاد) اشاره می‌کند —
                            // نگاه کن WidgetContentRenderer::renderHeaderNav. لایوت‌های
                            // هدر سراسری/مشترک بین همه شرکت‌ها هستند و در لحظه seed هیچ
                            // صفحه واقعی (owner_company_id-دار) وجود ندارد که بشود
                            // مستقیم به آن لینک داد؛ آدرس واقعی فقط در لحظه رندر برای
                            // هر شرکت مشخص حل می‌شود.
                            'item_fields' => [
                                ['key' => 'label', 'type' => 'text', 'label' => 'عنوان'],
                                [
                                    'key' => 'category_key',
                                    'type' => 'select',
                                    'label' => 'صفحه مقصد',
                                    'options' => [
                                        ['value' => 'home', 'label' => 'خانه'],
                                        ['value' => 'about', 'label' => 'درباره ما'],
                                        ['value' => 'services', 'label' => 'خدمات'],
                                        ['value' => 'contact', 'label' => 'تماس با ما'],
                                        ['value' => 'blog', 'label' => 'وبلاگ'],
                                        ['value' => 'login', 'label' => 'ورود'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Footer->value,
                'name' => 'فوتر',
                'icon' => 'o-rectangle-stack',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'copyright_text', 'type' => 'text', 'label' => 'متن کپی‌رایت'],
                        [
                            'key' => 'social_links',
                            'type' => 'repeater',
                            'label' => 'لینک‌های شبکه اجتماعی',
                            'item_fields' => [
                                ['key' => 'label', 'type' => 'text', 'label' => 'عنوان'],
                                ['key' => 'url', 'type' => 'text', 'label' => 'لینک'],
                            ],
                        ],
                        ['key' => 'contact_text', 'type' => 'textarea', 'label' => 'متن آدرس/تماس'],
                    ],
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            Widget::updateOrCreate(['widget_key' => $widget['widget_key']], $widget);
        }
    }
}
