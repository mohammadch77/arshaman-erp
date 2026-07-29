<div>
    <x-header
        title="{{ $record ? 'ویرایش کارمند' : 'کارمند جدید' }}"
        subtitle="اطلاعات پرسنلی و قرارداد"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام کامل" wire:model="full_name" :icon="theme_icon('employee')" required />
            <x-input label="کد ملی" wire:model="national_id" :icon="theme_icon('employee')" required />
            <x-input label="تلفن" wire:model="phone" :icon="theme_icon('phone')" />
            <x-textarea label="آدرس" wire:model="address" :icon="theme_icon('address')" rows="2" />
            <x-input label="سمت" wire:model="position" :icon="theme_icon('employee')" required />

            <x-jalali-date-select
                field="hire_date"
                label="تاریخ استخدام"
                :year="$jalaliParts['hire_date']['year']"
                :month="$jalaliParts['hire_date']['month']"
                required
            />

            <x-select
                label="نوع قرارداد"
                wire:model="contract_type"
                :options="$this->contractTypeOptions"
                option-value="id"
                option-label="name"
                placeholder="انتخاب نوع قرارداد"
                placeholder-value=""
                :icon="theme_icon('contract')"
                required
            />

            <x-jalali-date-select
                field="contract_start_date"
                label="شروع قرارداد"
                :year="$jalaliParts['contract_start_date']['year']"
                :month="$jalaliParts['contract_start_date']['month']"
                required
            />
            <x-jalali-date-select
                field="contract_end_date"
                label="پایان قرارداد (خالی = دائم)"
                :year="$jalaliParts['contract_end_date']['year']"
                :month="$jalaliParts['contract_end_date']['month']"
            />

            @if($this->isContractExpiringSoon)
                <div class="alert alert-warning">
                    <x-icon :name="theme_icon('warning')" class="w-5 h-5" />
                    <span>پایان قرارداد این کارمند کمتر از ۳۰ روز دیگر است.</span>
                </div>
            @endif

            <x-input label="حقوق پایه" wire:model="base_salary" type="number" step="0.01" :icon="theme_icon('money')" required />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('employees.index') }}" class="btn-ghost" />
                <x-button
                    label="{{ $record ? 'ذخیره تغییرات' : 'ساخت کارمند' }}"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>

    @if($record && $record->employment_status->value !== 'terminated')
        <x-card title="پایان همکاری" shadow class="max-w-2xl mt-6">
            <x-form wire:submit="terminate" class="gap-5">
                <x-jalali-date-select
                    field="terminationDate"
                    label="تاریخ پایان همکاری"
                    :year="$jalaliParts['terminationDate']['year']"
                    :month="$jalaliParts['terminationDate']['month']"
                    required
                />

                <x-slot:actions>
                    <x-button
                        label="ثبت پایان همکاری"
                        type="submit"
                        class="btn-error btn-outline"
                        :icon="theme_icon('terminate')"
                        spinner="terminate"
                        wire:confirm="آیا از پایان همکاری این کارمند مطمئن هستید؟"
                    />
                </x-slot:actions>
            </x-form>
        </x-card>
    @endif
</div>
