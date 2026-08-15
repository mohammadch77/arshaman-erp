{{--
    پنل «افزودن ویجت» — کلیک (نه درگ) روی هرکدام از ۱۰ ویجت پرکاربرد
    (config('sitebuilder.quick_add_widgets')) یک نود تازه به widgetTree اضافه
    می‌کند؛ مقصد یا ریشه صفحه است یا محفظه‌ای که از طریق دکمه «انتخاب به‌عنوان
    مقصد» روی خودِ نود محفظه در widget-tree-node انتخاب شده (activeContainerId).

    متغیرهای ورودی:
    - $quickAddWidgets: کالکشن Widget های کاتالوگ افزودن سریع، به همان ترتیب config
    - $activeContainerId / $activeContainerLabel: مقصد فعلی افزودن
    - $canEdit: اگر false باشد (operator روی صفحه‌ی published)، پنل اصلاً رندر نمی‌شود
--}}
@if($canEdit)
    <x-card shadow>
        <x-slot:title>
            <span class="inline-flex items-center gap-2">
                <x-icon :name="theme_icon('add')" class="w-4 h-4 text-base-content/60" />
                افزودن ویجت
            </span>
        </x-slot:title>

        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm text-base-content/70">
            <span>مقصد افزودن:</span>
            @if($activeContainerId)
                <x-badge value="{{ $activeContainerLabel }}" class="badge-primary badge-sm" />
                <x-button label="بازنشانی به ریشه" class="btn-ghost btn-xs" wire:click="setActiveContainer(null)" />
            @else
                <x-badge value="ریشه صفحه" class="badge-ghost badge-sm" />
            @endif
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-5">
            @foreach($quickAddWidgets as $widget)
                <button
                    type="button"
                    wire:click="addWidget('{{ $widget->widget_key }}')"
                    class="flex flex-col items-center gap-1.5 rounded-box border border-base-300 p-3 text-center transition hover:border-primary/50 hover:bg-primary/5"
                >
                    <x-icon :name="$widget->icon ?: theme_icon('page')" class="h-5 w-5 text-primary" />
                    <span class="text-xs font-medium">{{ $widget->name }}</span>
                </button>
            @endforeach
        </div>
    </x-card>
@endif
