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
                    ['key' => 'amount', 'label' => 'مدت'],
                    ['key' => 'leave_status', 'label' => 'وضعیت'],
                    ['key' => 'actions', 'label' => ''],
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

                @scope('cell_amount', $leave)
                    @if($leave->leave_type->isHourly())
                        <div class="flex items-center gap-1">
                            <x-icon :name="theme_icon('hourly')" class="w-4 h-4 text-base-content/60" />
                            <span>{{ \App\Support\Farsi::toDigits($leave->hours_count) }} ساعت</span>
                        </div>
                        <div class="text-xs text-base-content/60">
                            {{ \App\Support\Farsi::toDigits($leave->start_time) }}
                            تا
                            {{ \App\Support\Farsi::toDigits($leave->end_time) }}
                        </div>
                    @else
                        {{ \App\Support\Farsi::toDigits($leave->days_count) }} روز
                    @endif
                @endscope

                @scope('cell_leave_status', $leave)
                    <div class="flex flex-col gap-1">
                        <x-badge
                            value="{{ $leave->leave_status->label() }}"
                            class="{{ match($leave->leave_status->value) {
                                'approved' => 'badge-success',
                                'rejected' => 'badge-error',
                                default => 'badge-warning',
                            } }}"
                        />

                        {{-- دلیل رد فقط وقتی نمایش داده می‌شود که درخواست واقعاً رد
                             شده باشد و مدیر دلیلی نوشته باشد. --}}
                        @if($leave->leave_status->value === 'rejected' && filled($leave->rejection_reason))
                            <div class="flex items-start gap-1 text-xs text-error max-w-xs">
                                <x-icon :name="theme_icon('note')" class="w-4 h-4 shrink-0 mt-0.5" />
                                <span class="whitespace-pre-line">{{ $leave->rejection_reason }}</span>
                            </div>
                        @endif
                    </div>
                @endscope

                {{-- ویرایش و حذف فقط تا قبل از تصمیم مدیر. بعد از تأیید یا رد،
                     درخواست بخشی از داده‌ای است که جمع ماهانه و فیش حقوقی روی آن
                     حساب کرده‌اند و فقط قابل مشاهده است. --}}
                @scope('cell_actions', $leave)
                    @if($leave->isEditableByOwner())
                        <div class="flex gap-1 justify-end">
                            <x-button
                                :icon="theme_icon('edit')"
                                class="btn-ghost btn-circle btn-sm"
                                tooltip-left="ویرایش"
                                wire:click="edit('{{ $leave->id }}')"
                            />
                            <x-button
                                :icon="theme_icon('delete')"
                                class="btn-ghost btn-circle btn-sm text-error"
                                tooltip-left="حذف"
                                wire:click="delete('{{ $leave->id }}')"
                                wire:confirm="این درخواست مرخصی حذف شود؟"
                            />
                        </div>
                    @endif
                @endscope
            </x-table>
        </x-card>

        <x-modal
            wire:model="showForm"
            :title="$editingLeaveId ? 'ویرایش درخواست مرخصی' : 'درخواست مرخصی'"
            separator
        >
            <div class="grid gap-4">
                <x-select
                    label="نوع مرخصی"
                    wire:model.live="leave_type"
                    :options="$this->leaveTypeOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="انتخاب نوع"
                    placeholder-value=""
                    required
                />

                <x-jalali-date-select
                    field="start_date"
                    :label="$this->isHourly ? 'تاریخ' : 'از تاریخ'"
                    :year="$jalaliParts['start_date']['year']"
                    :month="$jalaliParts['start_date']['month']"
                    required
                />

                {{-- مرخصی ساعتی ذاتاً یک‌روزه است: به‌جای «تا تاریخ»، ساعت شروع و
                     پایان گرفته می‌شود و تاریخ پایان خودکار برابر تاریخ شروع
                     می‌شود. --}}
                @if($this->isHourly)
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="از ساعت" wire:model="start_time" type="time" :icon="theme_icon('hourly')" required />
                        <x-input label="تا ساعت" wire:model="end_time" type="time" :icon="theme_icon('hourly')" required />
                    </div>
                @else
                    <x-jalali-date-select
                        field="end_date"
                        label="تا تاریخ"
                        :year="$jalaliParts['end_date']['year']"
                        :month="$jalaliParts['end_date']['month']"
                        required
                    />
                @endif

                <x-textarea label="دلیل (اختیاری)" wire:model="reason" />
            </div>

            <x-slot:actions>
                <x-button label="انصراف" @click="$wire.showForm = false" />
                <x-button
                    :label="$editingLeaveId ? 'ذخیره تغییرات' : 'ثبت درخواست'"
                    class="btn-primary"
                    wire:click="save"
                    spinner="save"
                />
            </x-slot:actions>
        </x-modal>
    @endif
</div>
