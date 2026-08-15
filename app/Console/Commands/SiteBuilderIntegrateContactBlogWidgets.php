<?php

namespace App\Console\Commands;

use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * اصلاح یک‌باره صفحات واقعی که پیش از این Session از دموهای قدیمی تماس/وبلاگ
 * (با container جای‌گیر) ساخته شده‌اند. ویرایش SiteBuilderDemosExpansionSeeder
 * فقط کاتالوگ دمو را عوض می‌کند — widget_tree صفحاتی که از قبل با
 * CreatePageFromDemo ساخته شده‌اند مستقل و عیناً کپی‌شده است (نگاه کن
 * CreatePageFromDemo)، پس هیچ seed دوباره‌ای آن‌ها را لمس نمی‌کند. و چون
 * PageContentEditor (طبق تصمیم Session ۶) هیچ راه «افزودن ویجت جدید» ندارد،
 * کاربر هم نمی‌تواند این جایگزینی را دستی از ادیتور انجام دهد — این دستور
 * تنها راه است. idempotent: چون بعد از اولین اجرا دیگر هیچ نودی با این id ها
 * پیدا نمی‌شود، اجراهای بعدی چیزی تغییر نمی‌دهند.
 *
 * یک حالت دوم هم هست که replace ساده کافی نیست: صفحات واقعی دسته «تماس»
 * که از دموی «شعب متعدد» (که هرگز حتی placeholder فرم نداشت — نگاه کن
 * commit این Session، پیش از این فقط نقشه/گالری/FAQ داشت) ساخته شده‌اند
 * اصلاً هیچ نودی برای جایگزینی ندارند. برای این‌ها به‌جای replace، یک نود
 * contact_form واقعی به انتهای widget_tree اضافه می‌شود (فقط برای صفحات
 * منتشرشده‌ی دسته «تماس» که از قبل هیچ contact_form ندارند) — تنها راهی که
 * صفحه‌ی واقعی «تماس با ما» عملاً یک فرم کار‌کننده داشته باشد، چون ادیتور
 * فعلاً هیچ قابلیت «افزودن ویجت» ندارد.
 */
class SiteBuilderIntegrateContactBlogWidgets extends Command
{
    protected $signature = 'sitebuilder:integrate-contact-blog-widgets';

    protected $description = 'container های جای‌گیر قدیمی فرم تماس/فهرست وبلاگ را در صفحات واقعی موجود با ویجت یکپارچه واقعی جایگزین می‌کند';

    /**
     * @var array<string, array{id: string, widget_key: string, instance_label: string, values: array<string, mixed>}>
     */
    private const REPLACEMENTS = [
        'contact-fullmap-form-placeholder' => [
            'id' => 'contact-fullmap-form',
            'widget_key' => WidgetKey::ContactForm,
            'instance_label' => 'فرم تماس تمام‌عرض',
            'values' => ['section_title' => 'فرم تماس'],
        ],
        'blog-simple-list-placeholder' => [
            'id' => 'blog-simple-list',
            'widget_key' => WidgetKey::BlogPostList,
            'instance_label' => 'فهرست پست‌های وبلاگ ساده',
            'values' => ['posts_count' => '6', 'section_title' => ''],
        ],
        'blog-featured-list-placeholder' => [
            'id' => 'blog-featured-list',
            'widget_key' => WidgetKey::BlogPostList,
            'instance_label' => 'فهرست جدیدترین پست‌های وبلاگ',
            'values' => ['posts_count' => '3', 'section_title' => 'جدیدترین مقالات'],
        ],
        'blog-newsletter-list-placeholder' => [
            'id' => 'blog-newsletter-list',
            'widget_key' => WidgetKey::BlogPostList,
            'instance_label' => 'فهرست پست‌های وبلاگ خبرنامه',
            'values' => ['posts_count' => '9', 'section_title' => 'آخرین مطالب'],
        ],
    ];

    public function handle(WidgetContentRenderer $renderer): int
    {
        $pages = Page::withoutGlobalScopes()->with('demo.category')->get();

        $changed = 0;

        foreach ($pages as $page) {
            $tree = $page->widget_tree ?? [];
            [$tree, $wasReplaced] = $this->replaceInNodes($tree);

            $wasAppended = false;

            if ($this->needsAppendedContactForm($page, $tree)) {
                $tree[] = [
                    'id' => 'contact-form-appended-'.Str::random(8),
                    'widget_key' => WidgetKey::ContactForm->value,
                    'instance_label' => 'فرم تماس',
                    'values' => ['section_title' => 'فرم تماس'],
                    'children' => [],
                ];
                $wasAppended = true;
            }

            if (! $wasReplaced && ! $wasAppended) {
                continue;
            }

            $page->widget_tree = $tree;
            $page->content_html = $renderer->render($tree);
            $page->saveQuietly();
            $changed++;

            $action = $wasReplaced ? 'جایگزین شد' : 'فرم تماس اضافه شد';
            $this->info("{$action}: {$page->slug}");
        }

        $this->info("پایان: {$changed} صفحه اصلاح شد.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int|string, mixed>  $tree
     */
    private function needsAppendedContactForm(Page $page, array $tree): bool
    {
        if ($page->page_status?->value !== 'published') {
            return false;
        }

        if ($page->demo?->category?->category_key !== PageCategoryKey::Contact) {
            return false;
        }

        return ! $this->containsWidgetKey($tree, WidgetKey::ContactForm);
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     */
    private function containsWidgetKey(array $nodes, WidgetKey $key): bool
    {
        foreach ($nodes as $node) {
            if (! is_array($node) || ! isset($node['id'])) {
                continue;
            }

            if (($node['widget_key'] ?? null) === $key->value) {
                return true;
            }

            if (! empty($node['children']) && $this->containsWidgetKey($node['children'], $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     * @return array{0: array<int|string, mixed>, 1: bool}
     */
    private function replaceInNodes(array $nodes): array
    {
        $wasReplaced = false;

        foreach ($nodes as $index => $node) {
            if (! is_array($node) || ! isset($node['id'])) {
                // کلید ریشه‌ی 'theme' اینجا رد می‌شود (نه یک نود واقعی).
                continue;
            }

            $replacement = self::REPLACEMENTS[$node['id']] ?? null;

            if ($replacement !== null) {
                $nodes[$index] = [
                    'id' => $replacement['id'],
                    'widget_key' => $replacement['widget_key']->value,
                    'instance_label' => $replacement['instance_label'],
                    'values' => $replacement['values'],
                    'children' => [],
                ];
                $wasReplaced = true;

                continue;
            }

            if (! empty($node['children'])) {
                [$node['children'], $childReplaced] = $this->replaceInNodes($node['children']);
                $nodes[$index] = $node;
                $wasReplaced = $wasReplaced || $childReplaced;
            }
        }

        return [$nodes, $wasReplaced];
    }
}
