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
                ['key' => 'amount', 'label' => 'مدت'],
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

            @scope('cell_amount', $leave)
                @if($leave->leave_type->isHourly())
                    <div class="flex items-center gap-1">
                        <x-icon :name="theme_icon('hourly')" class="w-4 h-4 text-base-content/60" />
                        <span>{{ \App\Support\Farsi::durationFromHours($leave->hours_count) }}</span>
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
                <x-badge
                    value="{{ $leave->leave_status->label() }}"
                    class="{{ match($leave->leave_status->value) {
                        'approved' => 'badge-success',
                        'rejected' => 'badge-error',
                        default => 'badge-warning',
                    } }}"
                />
            @endscope

            @scope('cell_actions', $leave, $activeProcessLeaveIds)
                <div class="flex gap-1 justify-end">
                    {{-- دکمه دلیل فقط وقتی واقعاً دلیلی نوشته شده — نه یک دکمه
                         همیشه‌حاضر که مودال خالی باز کند. --}}
                    @if(filled($leave->reason))
                        <x-button
                            :icon="theme_icon('note')"
                            class="btn-ghost btn-circle btn-sm"
                            tooltip-left="مشاهده دلیل درخواست"
                            wire:click="showReason('{{ $leave->id }}')"
                        />
                    @endif

                    @if($leave->leave_status->value === 'rejected' && filled($leave->rejection_reason))
                        <x-button
                            :icon="theme_icon('reject')"
                            class="btn-ghost btn-circle btn-sm text-error"
                            tooltip-left="مشاهده دلیل رد"
                            wire:click="showRejectionReason('{{ $leave->id }}')"
                        />
                    @endif

                    @if($leave->leave_status->value === 'pending')
                        @if(in_array($leave->id, $activeProcessLeaveIds, true))
                            <x-badge
                                value="در حال بررسی در فرآیند"
                                class="badge-info"
                                :icon="theme_icon('process')"
                                tooltip-left="این درخواست از طریق فرایند سازمانی در حال بررسی است و مسیر مستقیم مسدود شده"
                            />
                        @else
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
                                wire:click="openReject('{{ $leave->id }}')"
                            />
                        @endif
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showReasonModal" :title="$reasonModalTitle" separator>
        <p class="whitespace-pre-line leading-relaxed">{{ $reasonModalBody }}</p>

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showReasonModal = false" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showRejectModal" title="رد درخواست مرخصی" separator>
        <x-textarea
            label="دلیل رد (اختیاری)"
            wire:model="rejectionReason"
            hint="اگر دلیلی بنویسید، خودِ کارمند آن را در پنل شخصی‌اش می‌بیند."
            rows="3"
        />

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.showRejectModal = false" />
            <x-button label="رد درخواست" class="btn-error" wire:click="reject" spinner="reject" />
        </x-slot:actions>
    </x-modal>
</div>
