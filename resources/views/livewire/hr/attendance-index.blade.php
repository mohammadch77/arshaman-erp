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
                ['key' => 'duration', 'label' => 'مدت این تردد'],
                ['key' => 'recorded_by', 'label' => 'ثبت‌شده توسط'],
                ['key' => 'actions', 'label' => ''],
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
                {{ $attendance->check_in_at ? \App\Support\Jalali::toDisplayTime($attendance->check_in_at) : '—' }}
            @endscope

            @scope('cell_check_out_at', $attendance)
                @if($attendance->check_out_at)
                    {{ \App\Support\Jalali::toDisplayTime($attendance->check_out_at) }}
                @else
                    <x-badge value="باز" class="badge-info badge-sm" />
                @endif
            @endscope

            {{-- مدت **همین تردد**، نه کارکرد روز. کسری/اضافه‌کاری فقط در سطح روز
                 معنا دارد (مجموع همه ترددهای آن روز) و در گزارش جمع ماهانه
                 دیده می‌شود. --}}
            @scope('cell_duration', $attendance)
                @if($attendance->duration_minutes === null)
                    —
                @else
                    {{ \App\Support\Farsi::toDigits(intdiv($attendance->duration_minutes, 60)) }}:{{ \App\Support\Farsi::toDigits(str_pad($attendance->duration_minutes % 60, 2, '0', STR_PAD_LEFT)) }}
                @endif
            @endscope

            @scope('cell_recorded_by', $attendance)
                <x-badge
                    value="{{ $attendance->recorded_by->label() }}"
                    class="{{ $attendance->recorded_by->value === 'self' ? 'badge-info' : 'badge-neutral' }}"
                />
            @endscope

            @scope('cell_actions', $attendance)
                <div class="flex justify-end">
                    <x-button
                        :icon="theme_icon('edit')"
                        class="btn-ghost btn-circle btn-sm"
                        tooltip-left="ویرایش"
                        wire:click="edit('{{ $attendance->id }}')"
                    />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showForm" :title="$editingAttendanceId ? 'ویرایش حضور' : 'ثبت حضور دستی'" separator>
        <div class="grid gap-4">
            {{-- در حالت ویرایش، کارمند و تاریخ قفل‌اند: عوض‌کردنشان یک رکورد
                 *دیگر* می‌ساخت (چون کلید یکتا employee_id + attendance_date است)
                 و رکورد فعلی دست‌نخورده می‌ماند — رفتاری که کاربر انتظارش را ندارد. --}}
            @if($editingAttendanceId)
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-base-content/60">کارمند</div>
                        <div class="font-medium">
                            {{ $this->employeeOptions->firstWhere('id', $formEmployeeId)?->full_name ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-base-content/60">تاریخ</div>
                        <div class="font-medium">{{ \App\Support\Jalali::toDisplay($attendance_date) }}</div>
                    </div>
                </div>
            @else
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
            @endif

            <x-input label="ساعت ورود" wire:model="check_in_time" type="time" :icon="theme_icon('check-in')" />
            <x-input label="ساعت خروج" wire:model="check_out_time" type="time" :icon="theme_icon('check-out')" />
        </div>

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.showForm = false" />
            <x-button
                :label="$editingAttendanceId ? 'ذخیره تغییرات' : 'ثبت'"
                class="btn-primary"
                wire:click="save"
                spinner="save"
            />
        </x-slot:actions>
    </x-modal>
</div>
