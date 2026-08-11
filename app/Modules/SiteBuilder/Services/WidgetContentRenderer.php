<?php

namespace App\Modules\SiteBuilder\Services;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use Illuminate\Support\Facades\Log;

/**
 * ساختار هر نود widget_tree:
 * ['id' => uuid, 'widget_key' => 'container'|'title'|'image', 'values' => [...], 'children' => [...نود...]]
 * فقط widget_key های تعریف‌شده در WidgetKey رندر می‌شوند؛ هر کلید ناشناخته
 * لاگ و از خروجی حذف می‌شود (نه throw، نه silent skip بی‌اثر).
 */
class WidgetContentRenderer
{
    public function render(array $widgetTree): string
    {
        $html = $this->renderNodes($widgetTree);

        // content_html هرگز خالی/NULL نباشد، حتی برای دموی بدون هیچ ویجتی.
        return $html !== '' ? $html : '<div class="sb-page-empty"></div>';
    }

    private function renderNodes(array $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= $this->renderNode($node);
        }

        return $html;
    }

    private function renderNode(array $node): string
    {
        $key = WidgetKey::tryFrom($node['widget_key'] ?? '');

        if ($key === null) {
            Log::warning('SiteBuilder: widget_key ناشناخته در widget_tree رد شد.', [
                'widget_key' => $node['widget_key'] ?? null,
                'id' => $node['id'] ?? null,
            ]);

            return '';
        }

        $values = $node['values'] ?? [];

        return match ($key) {
            WidgetKey::Container => $this->renderContainer($node),
            WidgetKey::Title => $this->renderTitle($values),
            WidgetKey::Image => $this->renderImage($values),
        };
    }

    private function renderContainer(array $node): string
    {
        $children = $this->renderNodes($node['children'] ?? []);

        return '<div class="sb-widget sb-widget-container">'.$children.'</div>';
    }

    private function renderTitle(array $values): string
    {
        $text = (string) ($values['text'] ?? '');
        $level = (int) ($values['level'] ?? 2);
        $level = max(1, min(6, $level));

        return "<h{$level} class=\"sb-widget sb-widget-title\">".e($text)."</h{$level}>";
    }

    private function renderImage(array $values): string
    {
        $src = (string) ($values['image_path'] ?? $values['src'] ?? '');

        if ($src === '') {
            return '';
        }

        $alt = (string) ($values['alt'] ?? '');

        return '<img class="sb-widget sb-widget-image" src="'.e($src).'" alt="'.e($alt).'">';
    }
}
