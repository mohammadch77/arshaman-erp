<?php

namespace App\Modules\SiteBuilder\Actions;

use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use App\Modules\SiteBuilder\Services\WidgetTreeValueMerger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdatePageWidgetValues
{
    public function __construct(
        private WidgetContentRenderer $renderer,
        private WidgetTreeValueMerger $merger,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $fieldValues  نگاشت widget instance id → [field key => مقدار]
     * @param  array<int, array<string, mixed>>|null  $widgetTree  اگر داده شود (مسیر PageContentEditor —
     *   کاربر پیش از ذخیره ترتیب نودها را با drag-and-drop در حافظه جابه‌جا کرده، نگاه کن
     *   WidgetTreeReorderer)، به‌جای widget_tree خام دیتابیس همین درخت پایه قرار می‌گیرد و
     *   $fieldValues رویش merge می‌شود. تعداد/نوع نودها همچنان دست‌کاری نمی‌شود، فقط ترتیب/محل
     *   ممکن است از قبل توسط drag-and-drop عوض شده باشد.
     */
    public function handle(
        Page $page,
        array $fieldValues,
        User $actor,
        ?string $extraCss = null,
        ?string $extraJs = null,
        ?PageStatus $pageStatus = null,
        ?array $widgetTree = null,
    ): Page {
        $changingStatus = $pageStatus !== null && $pageStatus !== $page->page_status;

        // ویرایش مقادیر ویجت، جابه‌جایی ساختار (drag-and-drop)، یا وضعیت انتشار
        // همان قید معمول update() را دارد (operator فقط روی draft). extra_css/extra_js
        // طبق DECISIONS.md از این قید مستثنی‌اند، پس همیشه جدا و بدون شرط draft بررسی می‌شوند.
        if (! empty($fieldValues) || $changingStatus || $widgetTree !== null) {
            Gate::forUser($actor)->authorize('update', $page);
        }

        if ($changingStatus) {
            Gate::forUser($actor)->authorize('canPublish', [Page::class, $page->owner_company_id]);
        }

        Gate::forUser($actor)->authorize('canEditExtraCode', [Page::class, $page->owner_company_id]);

        // فقط مقادیر داخل نودهای موجود جایگزین می‌شود — تعداد/نوع نودها هرگز از
        // این مسیر تغییر نمی‌کند (کاربر فقط فیلد پر می‌کند یا جابه‌جا می‌کند).
        // همان WidgetTreeValueMerger که پیش‌نمایش زنده PageContentEditor هم
        // استفاده می‌کند — یک منبع واحد، نه دو منطق جدا.
        $baseTree = $widgetTree ?? $page->widget_tree;
        $mergedTree = $this->merger->apply($baseTree, $fieldValues);

        DB::transaction(function () use ($page, $mergedTree, $extraCss, $extraJs, $pageStatus, $actor) {
            $page->update([
                'widget_tree' => $mergedTree,
                'content_html' => $this->renderer->render($mergedTree),
                'extra_css' => $extraCss,
                'extra_js' => $extraJs,
                'page_status' => $pageStatus ?? $page->page_status,
                'updated_by_user_id' => $actor->id,
            ]);
        });

        return $page->refresh();
    }
}
