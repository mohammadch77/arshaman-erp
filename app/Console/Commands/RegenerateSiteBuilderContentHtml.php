<?php

namespace App\Console\Commands;

use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\Console\Command;

/**
 * content_html هر صفحه یک snapshot است که فقط هنگام ذخیره (CreatePageFromDemo/
 * UpdatePageWidgetValues) از widget_tree ساخته می‌شود؛ هر تغییر بعدی در
 * WidgetContentRenderer (رفع باگ، CSS جدید، ویجت جدید) خودکار روی صفحات از قبل
 * ذخیره‌شده اثر نمی‌گذارد. این دستور همان بازسازی را برای همه صفحات موجود
 * دوباره اجرا می‌کند تا content_html با رندرر فعلی هماهنگ بماند.
 */
class RegenerateSiteBuilderContentHtml extends Command
{
    protected $signature = 'sitebuilder:regenerate-content-html';

    protected $description = 'content_html همه صفحات سایت‌ساز را از widget_tree موجودشان با رندرر فعلی دوباره می‌سازد';

    public function handle(WidgetContentRenderer $renderer): int
    {
        // withoutGlobalScopes: این یک فرآیند سراسری هلدینگ است، محدود به
        // CompanyContext یک session نیست (که در کانتکست CLI اصلاً وجود ندارد).
        $pages = Page::withoutGlobalScopes()->get();

        $updated = 0;
        $unchanged = 0;

        foreach ($pages as $page) {
            $newHtml = $renderer->render($page->widget_tree ?? []);

            if ($newHtml === $page->content_html) {
                $unchanged++;

                continue;
            }

            $page->content_html = $newHtml;
            $page->saveQuietly();
            $updated++;

            $this->info("به‌روز شد: {$page->slug}");
        }

        $this->info("پایان: {$updated} صفحه به‌روز شد، {$unchanged} صفحه بدون تغییر ماند.");

        return self::SUCCESS;
    }
}
