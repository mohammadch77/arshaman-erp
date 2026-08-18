<div>
    <x-header title="طراحی فرایندها" subtitle="تعریف گردش‌کار تأیید — مراحل و گذارها" separator>
        <x-slot:actions>
            <x-button label="فرایند جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('processes.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'name', 'label' => 'نام'],
                ['key' => 'subject_type', 'label' => 'نوع'],
                ['key' => 'active_instances_count', 'label' => 'در جریان'],
                ['key' => 'is_active', 'label' => 'وضعیت'],
            ]"
            :rows="$definitions"
            with-pagination
        >
            @scope('cell_subject_type', $definition)
                @if($definition->subject_type)
                    <x-badge value="{{ config('processes.subject_type_labels.'.$definition->subject_type, class_basename($definition->subject_type)) }}" class="badge-ghost" />
                @else
                    <x-badge value="فرایند آزاد" class="badge-info" />
                @endif
            @endscope

            @scope('cell_active_instances_count', $definition)
                {{ \App\Support\Farsi::toDigits((string) $definition->active_instances_count) }}
            @endscope

            @scope('cell_is_active', $definition)
                @if($definition->is_active)
                    <x-badge value="فعال" class="badge-success" />
                @else
                    <x-badge value="غیرفعال" class="badge-ghost" />
                @endif
            @endscope

            @scope('actions', $definition)
                <x-button
                    :icon="theme_icon('flowchart')"
                    tooltip-left="مشاهده فلوچارت"
                    class="btn-circle btn-ghost btn-sm"
                    wire:click="showFlowchart('{{ $definition->id }}')"
                />

                <x-button
                    :icon="theme_icon('edit')"
                    tooltip-left="ویرایش"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('processes.edit', $definition->id) }}"
                />

                <x-button
                    :icon="$definition->is_active ? theme_icon('deactivate') : theme_icon('activate')"
                    :tooltip-left="$definition->is_active ? 'غیرفعال‌کردن' : 'فعال‌کردن'"
                    class="btn-circle btn-ghost btn-sm"
                    wire:click="toggleActive('{{ $definition->id }}')"
                    spinner="toggleActive('{{ $definition->id }}')"
                />

                <x-button
                    :icon="theme_icon('delete')"
                    tooltip-left="حذف"
                    class="btn-circle btn-ghost btn-sm text-error"
                    wire:click="delete('{{ $definition->id }}')"
                    wire:confirm="{{ $definition->instances_count > 0 ? 'این فرایند سابقه‌ی اجرا دارد — به‌جای حذف کامل، بایگانی می‌شود (داده‌ی تاریخی/لاگ محفوظ می‌ماند و از فهرست فعال مخفی می‌شود). ادامه می‌دهید؟' : 'این فرایند هرگز اجرا نشده — کاملاً و برای همیشه حذف خواهد شد. ادامه می‌دهید؟' }}"
                    spinner="delete('{{ $definition->id }}')"
                />
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showFlowchartModal" title="فلوچارت فرایند" separator :box-class="'w-11/12 max-w-4xl'">
        <div
            x-data
            x-on:process-flowchart-ready.window="window.renderProcessFlowchart($refs.flowchartContainer, $event.detail.mermaid)"
            class="overflow-x-auto"
        >
            <div x-ref="flowchartContainer" class="flex justify-center min-h-[200px]"></div>
        </div>

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showFlowchartModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
