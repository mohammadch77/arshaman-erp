<div>
    <x-header title="مرخصی‌ها" subtitle="بررسی و تأیید/رد درخواست‌های مرخصی پرسنل" separator>
        <x-slot:actions>
            <x-select
                wire:model.live="filterEmployeeId"
                :options="$this->employeeOptions"
                option-value="id"
                option-label="full_name"
                placeholder="همه کارمندان"
                placeholder-value=""
            />
            <x-select
                wire:model.live="filterStatus"
                :options="$this->statusOptions"
                option-value="id"
                option-label="name"
            />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'employee', 'label' => 'کارمند'],
                ['key' => 'leave_type', 'label' => 'نوع مرخصی'],
                ['key' => 'start_date', 'label' => 'از تاریخ'],
                ['key' => 'end_date', 'label' => 'تا تاریخ'],
                ['key' => 'days_count', 'label' => 'تعداد روز'],
                ['key' => 'leave_status', 'label' => 'وضعیت'],
                ['key' => 'actions', 'label' => ''],
            ]"
            :rows="$leaves"
            with-pagination
        >
            @scope('cell_employee', $leave)
                {{ $leave->employee->full_name }}
            @endscope

            @scope('cell_leave_type', $leave)
                {{ $leave->leave_type->label() }}
            @endscope

            @scope('cell_start_date', $leave)
                {{ \App\Support\Jalali::toDisplay($leave->start_date) }}
            @endscope

            @scope('cell_end_date', $leave)
                {{ \App\Support\Jalali::toDisplay($leave->end_date) }}
            @endscope

            @scope('cell_days_count', $leave)
                {{ \App\Support\Farsi::toDigits($leave->days_count) }}
            @endscope

            @scope('cell_leave_status', $leave)
                <x-badge
                    value="{{ $leave->leave_status->label() }}"
                    class="{{ match($leave->leave_status->value) {
                        'approved' => 'badge-success',
                        'rejected' => 'badge-error',
                        default => 'badge-warning',
                    } }}"
                />
            @endscope

            @scope('cell_actions', $leave)
                @if($leave->leave_status->value === 'pending')
                    <div class="flex gap-1 justify-end">
                        <x-button
                            :icon="theme_icon('approve')"
                            class="btn-success btn-circle btn-sm"
                            tooltip-left="تأیید"
                            wire:click="approve('{{ $leave->id }}')"
                            wire:confirm="این مرخصی تأیید شود؟"
                        />
                        <x-button
                            :icon="theme_icon('reject')"
                            class="btn-error btn-circle btn-sm"
                            tooltip-left="رد"
                            wire:click="reject('{{ $leave->id }}')"
                            wire:confirm="این مرخصی رد شود؟"
                        />
                    </div>
                @endif
            @endscope
        </x-table>
    </x-card>
</div>
