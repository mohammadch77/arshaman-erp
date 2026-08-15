<?php

namespace App\Modules\SiteBuilder\Support;

/**
 * تبدیل مسیر ذخیره‌شده یک تصویر (روی دیسک public یا از قبل یک URL کامل/
 * root-relative) به src قابل رندر — استخراج‌شده از
 * WidgetContentRenderer::resolveImageUrl تا DynamicWidgetResolver هم (برای
 * تصویر شاخص پست وبلاگ در ویجت blog_post_list) بدون تکرار منطق از همین یک
 * منبع استفاده کند. نگاه کن کامنت اصلی در WidgetContentRenderer برای دلیل
 * root-relative بودن مسیر خروجی به‌جای Storage::url() خام.
 */
class StorageUrl
{
    public static function resolve(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('#^(https?://|//|/)#i', $path) === 1) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
