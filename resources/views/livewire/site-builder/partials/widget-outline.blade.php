{{--
    فهرست کوتاه («outline») همه‌ی ویجت‌های صفحه برای پرش سریع — وقتی فرم
    محتوا طولانی می‌شود (چند محفظه با چند ویجت داخل هر کدام)، پیداکردن دستی
    یک ویجت مشخص با اسکرول کند است. هر آیتم یک لینک به همان
    id="sb-node-anchor-{id}" روی widget-tree-node است.

    متغیر ورودی:
    - $nodes: همان $this->widgetTreeUi (درخت کامل و تودرتو)
--}}
@php
    $sbFlattenOutline = function (array $nodes, int $depth = 0) use (&$sbFlattenOutline): array {
        $items = [];

        foreach ($nodes as $node) {
            $items[] = ['id' => $node['id'], 'label' => $node['section_label'], 'depth' => $depth];
            $items = [...$items, ...$sbFlattenOutline($node['children'] ?? [], $depth + 1)];
        }

        return $items;
    };

    $sbOutlineItems = $sbFlattenOutline($nodes);
@endphp

@if(count($sbOutlineItems) > 1)
    <details class="rounded-box border border-base-300 bg-base-100 p-3">
        <summary class="flex cursor-pointer items-center gap-2 text-sm font-medium text-base-content/70">
            <x-icon :name="theme_icon('outline')" class="h-4 w-4" />
            فهرست ویجت‌های صفحه ({{ count($sbOutlineItems) }})
        </summary>

        <nav class="mt-3 flex flex-col gap-1">
            @foreach($sbOutlineItems as $item)
                <a
                    href="#sb-node-anchor-{{ $item['id'] }}"
                    class="rounded-btn px-2 py-1 text-sm text-base-content/70 transition hover:bg-base-200 hover:text-base-content"
                    style="padding-inline-start: {{ 0.5 + $item['depth'] * 1.25 }}rem"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </details>
@endif
