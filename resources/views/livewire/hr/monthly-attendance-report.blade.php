<div>
    <x-header title="جمع ماهانه کارکرد" subtitle="کارکرد، غیبت، تأخیر و اضافه‌کاری هر کارمند در یک ماه" separator>
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
                wire:model.live="year"
                :options="\App\Support\Jalali::yearOptions()"
                option-value="id"
                option-label="name"
                placeholder="سال"
            />
            <x-select
                wire:model.live="month"
                :options="\App\Support\Jalali::monthOptions()"
                option-value="id"
                option-label="name"
                placeholder="ماه"
            />
            <x-button label="محاسبه" :icon="theme_icon('calculate')" class="btn-primary" wire:click="calculate" spinner="calculate" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'employee', 'label' => 'کارمند'],
                ['key' => 'total_worked_days', 'label' => 'روز کارکرد'],
                ['key' => 'total_absent_days', 'label' => 'روز غیبت'],
                ['key' => 'total_late_minutes', 'label' => 'تأخیر (دقیقه)'],
                ['key' => 'total_overtime_minutes', 'label' => 'اضافه‌کاری (دقیقه)'],
                ['key' => 'total_leave_days', 'label' => 'روز مرخصی'],
                ['key' => 'calculated_at', 'label' => 'آخرین محاسبه'],
            ]"
            :rows="$summaries"
        >
            @scope('cell_employee', $summary)
                {{ $summary->employee->full_name }}
            @endscope

            @scope('cell_total_worked_days', $summary)
                {{ \App\Support\Farsi::toDigits($summary->total_worked_days) }}
            @endscope

            @scope('cell_total_absent_days', $summary)
                <span class="{{ $summary->total_absent_days > 0 ? 'text-error' : '' }}">
                    {{ \App\Support\Farsi::toDigits($summary->total_absent_days) }}
                </span>
            @endscope

            @scope('cell_total_late_minutes', $summary)
                {{ \App\Support\Farsi::toDigits($summary->total_late_minutes) }}
            @endscope

            @scope('cell_total_overtime_minutes', $summary)
                {{ \App\Support\Farsi::toDigits($summary->total_overtime_minutes) }}
            @endscope

            @scope('cell_total_leave_days', $summary)
                {{ \App\Support\Farsi::toDigits($summary->total_leave_days) }}
            @endscope

            @scope('cell_calculated_at', $summary)
                {{ $summary->calculated_at ? \App\Support\Jalali::toDisplay($summary->calculated_at) : '—' }}
            @endscope
        </x-table>
    </x-card>
</div>
