<?php

namespace App\Modules\SiteBuilder\Actions;

use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdatePageWidgetValues
{
    public function __construct(private WidgetContentRenderer $renderer) {}

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

        $editableFieldsByWidgetKey = Widget::query()->pluck('default_config', 'widget_key')
            ->map(fn (?array $config) => $config['editable_fields'] ?? [])
            ->all();

        // فقط مقادیر داخل نودهای موجود جایگزین می‌شود — تعداد/ترتیب/ساختار
        // widget_tree هرگز از این مسیر تغییر نمی‌کند (کاربر فقط فیلد پر می‌کند).
        $widgetTree = $this->applyValues($page->widget_tree, $fieldValues, $editableFieldsByWidgetKey);

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

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, array<string, mixed>>  $fieldValues
     * @param  array<string, array<int, array<string, string>>>  $editableFieldsByWidgetKey
     * @return array<int, array<string, mixed>>
     */
    private function applyValues(array $nodes, array $fieldValues, array $editableFieldsByWidgetKey): array
    {
        foreach ($nodes as &$node) {
            $nodeId = $node['id'] ?? null;
            $allowedKeys = array_column($editableFieldsByWidgetKey[$node['widget_key'] ?? ''] ?? [], 'key');

            if ($nodeId !== null && isset($fieldValues[$nodeId])) {
                foreach ($fieldValues[$nodeId] as $fieldKey => $fieldValue) {
                    if (in_array($fieldKey, $allowedKeys, true)) {
                        $node['values'][$fieldKey] = $fieldValue;
                    }
                }
            }

            if (! empty($node['children'])) {
                $node['children'] = $this->applyValues($node['children'], $fieldValues, $editableFieldsByWidgetKey);
            }
        }

        return $nodes;
    }
}
