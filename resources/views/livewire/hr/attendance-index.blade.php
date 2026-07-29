<div>
    <x-header title="حضور و غیاب" subtitle="ثبت و مشاهده ورود و خروج پرسنل" separator>
        <x-slot:actions>
            <x-select
                wire:model.live="filterEmployeeId"
                :options="$this->employeeOptions"
                option-value="id"
                option-label="full_name"
                placeholder="همه کارمندان"
                placeholder-value=""
            />
            <x-button label="ثبت حضور" :icon="theme_icon('add')" class="btn-primary" wire:click="openForm" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'employee', 'label' => 'کارمند'],
                ['key' => 'attendance_date', 'label' => 'تاریخ'],
                ['key' => 'check_in_at', 'label' => 'ورود'],
                ['key' => 'check_out_at', 'label' => 'خروج'],
                ['key' => 'late_minutes', 'label' => 'تأخیر (دقیقه)'],
                ['key' => 'overtime_minutes', 'label' => 'اضافه‌کاری (دقیقه)'],
                ['key' => 'recorded_by', 'label' => 'ثبت‌شده توسط'],
            ]"
            :rows="$attendances"
            with-pagination
        >
            @scope('cell_employee', $attendance)
                {{ $attendance->employee->full_name }}
            @endscope

            @scope('cell_attendance_date', $attendance)
                {{ \App\Support\Jalali::toDisplay($attendance->attendance_date) }}
            @endscope

            @scope('cell_check_in_at', $attendance)
                {{ $attendance->check_in_at ? \App\Support\Farsi::toDigits($attendance->check_in_at->format('H:i')) : '—' }}
            @endscope

            @scope('cell_check_out_at', $attendance)
                {{ $attendance->check_out_at ? \App\Support\Farsi::toDigits($attendance->check_out_at->format('H:i')) : '—' }}
            @endscope

            @scope('cell_late_minutes', $attendance)
                {{ \App\Support\Farsi::toDigits($attendance->late_minutes) }}
            @endscope

            @scope('cell_overtime_minutes', $attendance)
                {{ \App\Support\Farsi::toDigits($attendance->overtime_minutes) }}
            @endscope

            @scope('cell_recorded_by', $attendance)
                <x-badge
                    value="{{ $attendance->recorded_by->label() }}"
                    class="{{ $attendance->recorded_by->value === 'self' ? 'badge-info' : 'badge-neutral' }}"
                />
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showForm" title="ثبت حضور دستی" separator>
        <div class="grid gap-4">
            <x-select
                label="کارمند"
                wire:model="formEmployeeId"
                :options="$this->employeeOptions"
                option-value="id"
                option-label="full_name"
                placeholder="انتخاب کارمند"
                placeholder-value=""
                required
            />

            <x-jalali-date-select
                field="attendance_date"
                label="تاریخ"
                :year="$jalaliParts['attendance_date']['year']"
                :month="$jalaliParts['attendance_date']['month']"
                required
            />

            <x-input label="ساعت ورود" wire:model="check_in_time" type="time" :icon="theme_icon('check-in')" />
            <x-input label="ساعت خروج" wire:model="check_out_time" type="time" :icon="theme_icon('check-out')" />
        </div>

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.showForm = false" />
            <x-button label="ثبت" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>
