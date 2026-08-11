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
     */
    public function handle(
        Page $page,
        array $fieldValues,
        User $actor,
        ?string $extraCss = null,
        ?string $extraJs = null,
        ?PageStatus $pageStatus = null,
    ): Page {
        $changingStatus = $pageStatus !== null && $pageStatus !== $page->page_status;

        // ویرایش مقادیر ویجت یا وضعیت انتشار همان قید معمول update() را دارد
        // (operator فقط روی draft). extra_css/extra_js طبق DECISIONS.md از این
        // قید مستثنی‌اند، پس همیشه جدا و بدون شرط draft بررسی می‌شوند.
        if (! empty($fieldValues) || $changingStatus) {
            Gate::forUser($actor)->authorize('update', $page);
        }

        if ($changingStatus) {
            Gate::forUser($actor)->authorize('canPublish', [Page::class, $page->owner_company_id]);
        }

        Gate::forUser($actor)->authorize('canEditExtraCode', [Page::class, $page->owner_company_id]);

        // فقط مقادیر داخل نودهای موجود جایگزین می‌شود — تعداد/ترتیب/ساختار
        // widget_tree هرگز از این مسیر تغییر نمی‌کند (کاربر فقط فیلد پر می‌کند).
        // همان WidgetTreeValueMerger که پیش‌نمایش زنده PageContentEditor هم
        // استفاده می‌کند — یک منبع واحد، نه دو منطق جدا.
        $widgetTree = $this->merger->apply($page->widget_tree, $fieldValues);

        DB::transaction(function () use ($page, $widgetTree, $extraCss, $extraJs, $pageStatus, $actor) {
            $page->update([
                'widget_tree' => $widgetTree,
                'content_html' => $this->renderer->render($widgetTree),
                'extra_css' => $extraCss,
                'extra_js' => $extraJs,
                'page_status' => $pageStatus ?? $page->page_status,
                'updated_by_user_id' => $actor->id,
            ]);
        });

        return $page->refresh();
    }
}
