{{--
    یک لیست sortable — هم برای سطح بالای صفحه (parentId=null) هم برای فرزندان
    هر محفظه (parentId=id همان محفظه) استفاده می‌شود؛ همه با همان group name
    ('sb-widgets' — نگاه کن resources/js/sitebuilder-sortable.js) به هم متصل‌اند
    تا یک ویجت از هر لیست به هر لیست دیگری قابل‌کشیدن باشد.

    متغیرهای ورودی:
    - $nodes: آرایه نودهای این سطح (از widgetTreeUi یا children یک نود)
    - $canEdit: اگر false باشد (operator روی صفحه‌ی published)، اصلاً sortable فعال نمی‌شود
    - $parentId: شناسه محفظه‌ی صاحب این لیست، یا null برای سطح بالای صفحه
--}}
@php($parentId = $parentId ?? null)
<div
    class="sb-tree-list flex flex-col gap-3"
    data-sortable-list
    data-parent-id="{{ $parentId ?? '' }}"
    wire:key="sb-tree-{{ $parentId ?? 'root' }}"
    @if($canEdit)
        x-init="window.initSitebuilderSortable($el, {
            group: 'sb-widgets',
            onDrop: (draggedId, targetParentId, index) => $wire.moveWidgetNode(draggedId, targetParentId || null, index),
        })"
    @endif
>
    @forelse($nodes as $node)
        @include('livewire.site-builder.partials.widget-tree-node', ['node' => $node, 'canEdit' => $canEdit])
    @empty
        <div class="rounded-box border border-dashed border-base-300 p-4 text-center text-xs text-base-content/40" data-sortable-placeholder>
            {{ $parentId === null ? 'این دمو هیچ ویجتی ندارد.' : 'ویجتی را اینجا رها کن' }}
        </div>
    @endforelse
</div>
