<?php

namespace Database\Seeders;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * سه ویجت جدید Session ۹: دو ویجت کاتالوگ «افزودن سریع» (text_editor,
 * slider) + یک ویجت یکپارچه CRM (customer_signup_form، الگوی دقیق
 * contact_form). widgets سراسری‌اند (بدون owner_company_id)، پس
 * updateOrCreate امن است — append-only، بدون ویرایش seederهای قبلی.
 *
 * ویجت icon که این‌جا اضافه شده بود در یک Session بعدی طبق نظر کارفرما کامل
 * حذف شد (نگاه کن WidgetKey enum و SiteBuilderRemoveIconWidgetSeeder) —
 * دیگر اینجا تعریف نمی‌شود.
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
                        [
                            'key' => 'text_align',
                            'type' => 'select',
                            'label' => 'تراز متن',
                            'default' => 'right',
                            'options' => [
                                ['value' => 'right', 'label' => 'راست‌چین'],
                                ['value' => 'left', 'label' => 'چپ‌چین'],
                                ['value' => 'center', 'label' => 'وسط‌چین'],
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
