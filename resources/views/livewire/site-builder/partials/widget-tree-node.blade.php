{{--
    یک نود درخت (هر ویجت، محفظه یا برگ) — کارت فیلدها + (اگر محفظه است) یک
    widget-tree تودرتوی دیگر برای فرزندانش. data-node-id همان شناسه‌ای است که
    JS سمت کلاینت (sitebuilder-sortable.js) موقع drop به moveWidgetNode() پاس می‌دهد.
--}}
@php($isContainer = $node['is_container'])
<div
    class="sb-tree-node rounded-box border border-base-300 bg-base-100"
    data-node-id="{{ $node['id'] }}"
    wire:key="sb-node-{{ $node['id'] }}"
>
    <x-card shadow class="{{ $isContainer ? '!rounded-b-none' : '' }}">
        <x-slot:title>
            <div class="flex items-center gap-2">
                @if($canEdit)
                    <span class="sb-drag-handle cursor-grab text-base-content/40 transition hover:text-base-content/70 active:cursor-grabbing" title="جابه‌جایی با درگ">
                        <x-icon :name="theme_icon('drag-handle')" class="h-4 w-4" />
                    </span>
                @endif
                <span class="text-sm font-semibold text-base-content/70">{{ $node['section_label'] }}</span>
                @if($isContainer)
                    <x-badge value="محفظه" class="badge-ghost badge-sm" />
                @endif
            </div>
        </x-slot:title>

        @if(! empty($node['fields']))
            <div class="flex flex-col gap-4">
                @include('livewire.site-builder.partials.widget-fields', ['node' => $node, 'canEdit' => $canEdit])
            </div>
        @endif
    </x-card>

    @if($isContainer)
        <div class="rounded-b-box border border-t-0 border-base-300 bg-base-200/40 p-3">
            @include('livewire.site-builder.partials.widget-tree', ['nodes' => $node['children'], 'canEdit' => $canEdit, 'parentId' => $node['id']])
        </div>
    @endif
</div>
