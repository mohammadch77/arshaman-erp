<div>
    <x-header title="پرسنل" subtitle="فهرست کارمندان، وضعیت استخدام و قرارداد" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام یا کد ملی..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select
                wire:model.live="employmentStatus"
                :options="$this->employmentStatusOptions"
                option-value="id"
                option-label="name"
                placeholder="همه وضعیت‌ها"
                placeholder-value=""
            />
            <x-select
                wire:model.live="contractType"
                :options="$this->contractTypeOptions"
                option-value="id"
                option-label="name"
                placeholder="همه قراردادها"
                placeholder-value=""
            />
            <x-button label="کارمند جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('employees.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'full_name', 'label' => 'نام'],
                ['key' => 'position', 'label' => 'سمت'],
                ['key' => 'employment_status', 'label' => 'وضعیت استخدام'],
                ['key' => 'contract_type', 'label' => 'نوع قرارداد'],
                ['key' => 'contract_end_date', 'label' => 'پایان قرارداد'],
            ]"
            :rows="$employees"
            with-pagination
        >
            @scope('cell_employment_status', $employee)
                <x-badge
                    value="{{ $employee->employment_status->label() }}"
                    class="{{ match ($employee->employment_status->value) {
                        'active' => 'badge-success',
                        'on_leave' => 'badge-warning',
                        'terminated' => 'badge-error',
                    } }}"
                />
            @endscope

            @scope('cell_contract_type', $employee)
                {{ $employee->contract_type->label() }}
            @endscope

            @scope('cell_contract_end_date', $employee)
                @if($employee->contract_end_date)
                    <div class="flex items-center gap-2">
                        <span>{{ \App\Support\Jalali::toDisplay($employee->contract_end_date) }}</span>
                        @if($employee->isContractExpiringSoon())
                            <x-icon :name="theme_icon('warning')" class="text-warning w-4 h-4" tooltip="پایان قرارداد نزدیک است" />
                        @endif
                    </div>
                @else
                    <span class="text-base-content/50">دائم</span>
                @endif
            @endscope

            @scope('actions', $employee)
                <x-button
                    :icon="theme_icon('edit')"
                    tooltip-left="ویرایش"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('employees.edit', $employee->id) }}"
                />
            @endscope
        </x-table>
    </x-card>
</div>
