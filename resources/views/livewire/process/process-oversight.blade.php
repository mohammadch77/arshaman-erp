<div>
    <x-header title="نظارت بر فرایندها" subtitle="همه‌ی درخواست‌های در جریان/تمام‌شده‌ی این شرکت" separator />

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'definition', 'label' => 'فرایند'],
                ['key' => 'started_by', 'label' => 'شروع‌شده توسط'],
                ['key' => 'current_step', 'label' => 'مرحله‌ی فعلی'],
                ['key' => 'duration', 'label' => 'مدت در این مرحله'],
                ['key' => 'status', 'label' => 'وضعیت'],
            ]"
            :rows="$instances"
            with-pagination
        >
            @scope('cell_definition', $instance)
                <span class="inline-flex items-center gap-2">
                    {{ $instance->definition->name }}
                    @if($instance->definition->trashed())
                        <x-badge value="بایگانی‌شده" class="badge-ghost badge-sm" :icon="theme_icon('archive')" />
                    @endif
                </span>
            @endscope

            @scope('cell_started_by', $instance)
                {{ $instance->startedBy->full_name }}
            @endscope

            @scope('cell_current_step', $instance)
                @if($instance->status->value === 'in_progress')
                    {{ $instance->currentStep?->name }}
                @else
                    —
                @endif
            @endscope

            @scope('cell_duration', $instance)
                @if($instance->status->value === 'in_progress')
                    {{ $this->durationInCurrentStep($instance) }}
                @else
                    —
                @endif
            @endscope

            @scope('cell_status', $instance)
                @php($badge = match ($instance->status) {
                    \App\Modules\Process\Enums\ProcessStatus::InProgress => 'badge-info',
                    \App\Modules\Process\Enums\ProcessStatus::Approved => 'badge-success',
                    \App\Modules\Process\Enums\ProcessStatus::Rejected => 'badge-error',
                    \App\Modules\Process\Enums\ProcessStatus::Cancelled => 'badge-ghost',
                })
                <x-badge value="{{ $instance->status->label() }}" class="{{ $badge }}" />
            @endscope

            @scope('actions', $instance)
                <x-button
                    :icon="theme_icon('history')"
                    tooltip-left="تاریخچه"
                    class="btn-circle btn-ghost btn-sm"
                    wire:click="openHistory('{{ $instance->id }}')"
                />

                @if($instance->status->value === 'in_progress')
                    <x-button
                        :icon="theme_icon('reminder')"
                        tooltip-left="یادآوری"
                        class="btn-circle btn-ghost btn-sm"
                        wire:click="openReminder('{{ $instance->id }}')"
                    />
                @endif
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="reminderInstanceId" title="یادآوری به مسئول مرحله‌ی فعلی" separator>
        <x-textarea
            label="متن یادآوری"
            wire:model="reminderComment"
            :icon="theme_icon('reminder')"
            rows="3"
            placeholder="مثلاً: لطفاً هرچه سریع‌تر این درخواست را بررسی کنید..."
            required
        />

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.reminderInstanceId = null" />
            <x-button label="ارسال یادآوری" :icon="theme_icon('send')" class="btn-warning" wire:click="sendReminder" spinner="sendReminder" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showHistoryModal" title="تاریخچه‌ی فرایند" separator>
        @include('livewire.process.partials.history-list', ['events' => $this->history])

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showHistoryModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
