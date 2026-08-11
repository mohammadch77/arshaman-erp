<?php

namespace Database\Seeders;

use App\Modules\SiteBuilder\Enums\LayoutType;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\LayoutDemo;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * ویجت‌ها/دسته‌بندی‌ها سراسری‌اند و همیشه seed می‌شوند. این Session فقط یک
 * دموی نمونه برای دسته 'about' + یک دموی هدر/فوتر نمونه دارد — دموهای واقعی
 * و متعدد برای بقیه شش دسته در Session بعدی طراحی می‌شوند.
 */
class SiteBuilderSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = [
            [
                'widget_key' => WidgetKey::Container->value,
                'name' => 'محفظه',
                'icon' => 'o-squares-2x2',
                'default_config' => ['editable_fields' => []],
            ],
            [
                'widget_key' => WidgetKey::Title->value,
                'name' => 'عنوان',
                'icon' => 'o-bars-3-bottom-right',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'text', 'type' => 'text', 'label' => 'متن عنوان'],
                    ],
                ],
            ],
            [
                'widget_key' => WidgetKey::Image->value,
                'name' => 'تصویر',
                'icon' => 'o-photo',
                'default_config' => [
                    'editable_fields' => [
                        ['key' => 'image_path', 'type' => 'image', 'label' => 'تصویر'],
                        ['key' => 'alt', 'type' => 'text', 'label' => 'متن جایگزین تصویر'],
                    ],
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            Widget::updateOrCreate(['widget_key' => $widget['widget_key']], $widget);
        }

        foreach (PageCategoryKey::cases() as $key) {
            PageCategory::updateOrCreate(
                ['category_key' => $key->value],
                ['name' => $key->label()]
            );
        }

        $aboutCategory = PageCategory::where('category_key', PageCategoryKey::About->value)->firstOrFail();

        PageDemo::updateOrCreate(
            ['page_category_id' => $aboutCategory->id, 'name' => 'دموی نمونه درباره ما'],
            [
                'thumbnail_path' => null,
                'widget_tree' => [
                    [
                        'id' => 'about-hero-title',
                        'widget_key' => WidgetKey::Title->value,
                        // instance_label: تعیین‌شده توسط تیم فنی هنگام ساخت دمو — جدا از نام
                        // عمومی نوع ویجت (widgets.name)، برای تمایز چند نمونه از یک نوع ویجت
                        // در فرم PageContentEditor.
                        'instance_label' => 'عنوان اصلی صفحه',
                        'values' => ['text' => 'درباره ما', 'level' => 1],
                        'children' => [],
                    ],
                    [
                        'id' => 'about-content-container',
                        'widget_key' => WidgetKey::Container->value,
                        'instance_label' => 'محفظه بخش داستان ما',
                        'values' => [],
                        'children' => [
                            [
                                'id' => 'about-content-title',
                                'widget_key' => WidgetKey::Title->value,
                                'instance_label' => 'عنوان بخش داستان ما',
                                'values' => ['text' => 'داستان ما', 'level' => 2],
                                'children' => [],
                            ],
                            [
                                'id' => 'about-content-image',
                                'widget_key' => WidgetKey::Image->value,
                                'instance_label' => 'تصویر بخش داستان ما',
                                'values' => ['image_path' => null, 'alt' => 'تصویر درباره ما'],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ]
        );

        LayoutDemo::updateOrCreate(
            ['layout_type' => LayoutType::Header->value, 'name' => 'دموی نمونه هدر'],
            [
                'thumbnail_path' => null,
                'widget_tree' => [
                    [
                        'id' => 'header-site-title',
                        'widget_key' => WidgetKey::Title->value,
                        'instance_label' => 'نام سایت در هدر',
                        'values' => ['text' => 'نام سایت', 'level' => 3],
                        'children' => [],
                    ],
                ],
            ]
        );

        LayoutDemo::updateOrCreate(
            ['layout_type' => LayoutType::Footer->value, 'name' => 'دموی نمونه فوتر'],
            [
                'thumbnail_path' => null,
                'widget_tree' => [
                    [
                        'id' => 'footer-copyright-title',
                        'widget_key' => WidgetKey::Title->value,
                        'instance_label' => 'متن کپی‌رایت فوتر',
                        'values' => ['text' => 'تمامی حقوق محفوظ است', 'level' => 4],
                        'children' => [],
                    ],
                ],
            ]
        );
    }
}
