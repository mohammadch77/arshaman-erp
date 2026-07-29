<div>
    <x-header title="مرخصی‌های من" subtitle="درخواست مرخصی و پیگیری وضعیت آن" separator>
        @if($employeeId)
            <x-slot:actions>
                <x-button label="درخواست مرخصی" :icon="theme_icon('add')" class="btn-primary" wire:click="openForm" responsive />
            </x-slot:actions>
        @endif
    </x-header>

    @if(! $employeeId)
        <x-card shadow>
            <div class="flex items-center gap-2 text-base-content/70">
                <x-icon :name="theme_icon('warning')" class="w-5 h-5" />
                <span>شما پرونده پرسنلی ندارید. برای درخواست مرخصی باید ابتدا به یک پرونده کارمندی وصل شوید.</span>
            </div>
        </x-card>
    @else
        <x-card shadow>
            <x-table
                :headers="[
                    ['key' => 'leave_type', 'label' => 'نوع مرخصی'],
                    ['key' => 'start_date', 'label' => 'از تاریخ'],
                    ['key' => 'end_date', 'label' => 'تا تاریخ'],
                    ['key' => 'days_count', 'label' => 'تعداد روز'],
                    ['key' => 'leave_status', 'label' => 'وضعیت'],
                ]"
                :rows="$leaves"
            >
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
            </x-table>
        </x-card>

        <x-modal wire:model="showForm" title="درخواست مرخصی" separator>
            <div class="grid gap-4">
                <x-select
                    label="نوع مرخصی"
                    wire:model="leave_type"
                    :options="$this->leaveTypeOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="انتخاب نوع"
                    placeholder-value=""
                    required
                />

                <x-jalali-date-select
                    field="start_date"
                    label="از تاریخ"
                    :year="$jalaliParts['start_date']['year']"
                    :month="$jalaliParts['start_date']['month']"
                    required
                />

                <x-jalali-date-select
                    field="end_date"
                    label="تا تاریخ"
                    :year="$jalaliParts['end_date']['year']"
                    :month="$jalaliParts['end_date']['month']"
                    required
                />

                <x-textarea label="دلیل (اختیاری)" wire:model="reason" />
            </div>

            <x-slot:actions>
                <x-button label="انصراف" @click="$wire.showForm = false" />
                <x-button label="ثبت درخواست" class="btn-primary" wire:click="save" spinner="save" />
            </x-slot:actions>
        </x-modal>
    @endif
</div>
