<?php

namespace Database\Seeders;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * دو ویجت یکپارچه واقعی — contact_form (embed فرم Livewire واقعی
 * App\Livewire\CRM\Public\ContactForm) و blog_post_list (کوئری زنده
 * BlogPost::published() همان شرکت). برخلاف ۱۳ ویجت قبلی، این دو در لحظه‌ی
 * ذخیره content_html تولید نمی‌کنند — نگاه کن
 * App\Modules\SiteBuilder\Services\DynamicWidgetResolver. seeder جدید و
 * append-only، طبق الگوی همیشگی این ماژول (SiteBuilderWidgetsExpansionSeeder
 * دست‌نخورده می‌ماند).
 */
class SiteBuilderIntegratedWidgetsSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = [
            [
                'widget_key' => WidgetKey::ContactForm->value,
                'name' => 'فرم تماس',
                'icon' => 'o-envelope',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'section_title', 'type' => 'text', 'label' => 'عنوان بالای فرم (اختیاری)'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::BlogPostList->value,
                'name' => 'فهرست پست‌های وبلاگ',
                'icon' => 'o-newspaper',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'posts_count', 'type' => 'text', 'label' => 'تعداد پست نمایشی'],
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
