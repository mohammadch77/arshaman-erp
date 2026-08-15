<?php

namespace App\Modules\SiteBuilder\Services;

use App\Modules\SiteBuilder\Models\Widget;
use Mews\Purifier\Facades\Purifier;

/**
 * جایگزینی مقادیر داخل widget_tree — منبع مشترک بین ذخیره واقعی
 * (UpdatePageWidgetValues) و پیش‌نمایش زنده (PageContentEditor::refreshPreview)
 * تا هر دو مسیر دقیقاً همان قانون whitelist فیلد را رعایت کنند و هرگز از هم
 * جدا نیفتند.
 */
class WidgetTreeValueMerger
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, array<string, mixed>>  $fieldValues  نگاشت widget instance id → [field key => مقدار]
     * @return array<int, array<string, mixed>>
     *
     * نکته: $nodes ممکن است یک کلید رشته‌ای اضافه 'theme' هم داشته باشد (رنگ/فونت
     * سطح صفحه — نگاه کن WidgetContentRenderer::render). چون آن مقدار نه 'id' دارد
     * نه 'children'، applyValues() بی‌هیچ تغییری از کنارش رد می‌شود و همان‌طور
     * دست‌نخورده در خروجی باقی می‌ماند — نیازی به کد اضافه برای عبور آن نیست.
     */
    public function apply(array $nodes, array $fieldValues): array
    {
        $editableFieldsByWidgetKey = Widget::query()->pluck('default_config', 'widget_key')
            ->map(fn (?array $config) => $config['editable_fields'] ?? [])
            ->all();

        return $this->applyValues($nodes, $fieldValues, $editableFieldsByWidgetKey);
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
            $fieldsByKey = array_column($editableFieldsByWidgetKey[$node['widget_key'] ?? ''] ?? [], null, 'key');

            if ($nodeId !== null && isset($fieldValues[$nodeId])) {
                foreach ($fieldValues[$nodeId] as $fieldKey => $fieldValue) {
                    if (! isset($fieldsByKey[$fieldKey])) {
                        continue;
                    }

                    // نقطه‌ی مرکزی sanitize فیلدهای richtext (ویجت text_editor) —
                    // هم ذخیره واقعی (UpdatePageWidgetValues) هم پیش‌نمایش زنده
                    // (PageContentEditor::refreshPreview) از همین متد رد می‌شوند،
                    // پس هرگز یک مسیر پاک‌سازی‌نشده باقی نمی‌ماند.
                    if (($fieldsByKey[$fieldKey]['type'] ?? null) === 'richtext') {
                        $fieldValue = Purifier::clean((string) $fieldValue);
                    }

                    $node['values'][$fieldKey] = $fieldValue;
                }
            }

            if (! empty($node['children'])) {
                $node['children'] = $this->applyValues($node['children'], $fieldValues, $editableFieldsByWidgetKey);
            }
        }

        return $nodes;
    }
}
