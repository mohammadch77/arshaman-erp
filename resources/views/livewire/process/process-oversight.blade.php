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
        @if($this->history->isEmpty())
            <p class="text-base-content/60">هنوز هیچ رویدادی ثبت نشده است.</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach($this->history as $event)
                    <li class="border-b border-base-300 pb-3 last:border-b-0 last:pb-0">
                        <div class="flex items-center gap-2">
                            <x-icon :name="theme_icon('history')" class="w-4 h-4 text-base-content/60" />
                            <span class="font-medium">{{ $event->step->name }}</span>
                            <span class="badge badge-ghost badge-sm">{{ $event->action->label() }}</span>
                            @if($event->reversed_at)
                                <span class="badge badge-warning badge-sm">بازگردانی‌شده</span>
                            @endif
                        </div>

                        <div class="text-sm mt-1 text-base-content/70">
                            {{ $event->actor?->full_name ?? 'خودکار (سیستم)' }}
                        </div>

                        @if($event->comment)
                            <p class="text-sm text-base-content/70 mt-1 whitespace-pre-line">{{ $event->comment }}</p>
                        @endif

                        <div class="text-xs text-base-content/60 mt-1">
                            {{ \App\Support\Jalali::toDisplayDateTime($event->created_at) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showHistoryModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
