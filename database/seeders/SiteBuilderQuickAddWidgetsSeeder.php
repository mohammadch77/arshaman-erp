<?php

namespace Database\Seeders;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * چهار ویجت جدید Session ۹: سه ویجت کاتالوگ «افزودن سریع» (text_editor,
 * icon, slider) + یک ویجت یکپارچه CRM (customer_signup_form، الگوی دقیق
 * contact_form). widgets سراسری‌اند (بدون owner_company_id)، پس
 * updateOrCreate امن است — append-only، بدون ویرایش seederهای قبلی.
 */
class SiteBuilderQuickAddWidgetsSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = [
            [
                'widget_key' => WidgetKey::TextEditor->value,
                'name' => 'متن غنی',
                'icon' => 'o-pencil-square',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'html', 'type' => 'richtext', 'label' => 'متن'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Icon->value,
                'name' => 'آیکون',
                'icon' => 'o-sparkles',
                'default_config' => [
                    'editable_fields' => [
                        [
                            'key' => 'icon_name',
                            'type' => 'select',
                            'label' => 'آیکون',
                            'options' => [
                                ['value' => 'o-star', 'label' => 'ستاره'],
                                ['value' => 'o-shield-check', 'label' => 'محافظت'],
                                ['value' => 'o-sparkles', 'label' => 'درخشش'],
                                ['value' => 'o-bolt', 'label' => 'برق'],
                                ['value' => 'o-heart', 'label' => 'قلب'],
                                ['value' => 'o-check-circle', 'label' => 'تیک'],
                                ['value' => 'o-globe-alt', 'label' => 'جهان'],
                                ['value' => 'o-light-bulb', 'label' => 'لامپ'],
                                ['value' => 'o-rocket-launch', 'label' => 'موشک'],
                                ['value' => 'o-trophy', 'label' => 'جام'],
                                ['value' => 'o-hand-thumb-up', 'label' => 'پسندیدن'],
                                ['value' => 'o-clock', 'label' => 'ساعت'],
                                ['value' => 'o-gift', 'label' => 'هدیه'],
                                ['value' => 'o-face-smile', 'label' => 'لبخند'],
                            ],
                        ],
                        [
                            'key' => 'size',
                            'type' => 'select',
                            'label' => 'اندازه',
                            'default' => 'md',
                            'options' => [
                                ['value' => 'sm', 'label' => 'کوچک'],
                                ['value' => 'md', 'label' => 'متوسط'],
                                ['value' => 'lg', 'label' => 'بزرگ'],
                            ],
                        ],
                        [
                            'key' => 'color',
                            'type' => 'select',
                            'label' => 'رنگ',
                            'default' => 'primary',
                            'options' => [
                                ['value' => 'primary', 'label' => 'اصلی'],
                                ['value' => 'secondary', 'label' => 'ثانویه'],
                                ['value' => 'muted', 'label' => 'خنثی'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Slider->value,
                'name' => 'اسلایدر تصاویر',
                'icon' => 'o-photo',
                'default_config' => [
                    'editable_fields' => [
                        [
                            'key' => 'slides',
                            'type' => 'repeater',
                            'label' => 'اسلایدها',
                            'item_fields' => [
                                ['key' => 'image_path', 'type' => 'image', 'label' => 'تصویر'],
                                ['key' => 'title', 'type' => 'text', 'label' => 'عنوان (اختیاری)'],
                                ['key' => 'text', 'type' => 'textarea', 'label' => 'متن (اختیاری)'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::CustomerSignupForm->value,
                'name' => 'فرم ثبت‌نام مشتری',
                'icon' => 'o-user-plus',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'section_title', 'type' => 'text', 'label' => 'عنوان بخش (اختیاری)'],
                    ],
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            Widget::updateOrCreate(['widget_key' => $widget['widget_key']], $widget);
        }
    }
}
