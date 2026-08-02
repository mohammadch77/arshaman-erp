<div>
    <x-header
        title="{{ $record ? 'ویرایش طرف‌حساب' : 'طرف‌حساب جدید' }}"
        subtitle="اطلاعات مشتری یا تأمین‌کننده"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام" wire:model="name" :icon="theme_icon('party')" required />

            <x-select
                label="نوع شخص"
                wire:model="party_type"
                :options="$this->partyTypeOptions"
                option-value="id"
                option-label="name"
                required
            />

            <div class="flex items-center gap-6">
                <x-checkbox label="مشتری" wire:model="is_customer" />
                <x-checkbox label="تأمین‌کننده" wire:model="is_supplier" />
            </div>
            @error('is_customer')
                <div class="text-error text-sm">{{ $message }}</div>
            @enderror

            <x-input label="تلفن" wire:model="phone" :icon="theme_icon('phone')" />
            <x-input label="ایمیل" wire:model="email" type="email" :icon="theme_icon('email')" />
            <x-input label="کد اقتصادی" wire:model="economic_code" :icon="theme_icon('economic-code')" />
            <x-textarea label="آدرس" wire:model="address" :icon="theme_icon('address')" rows="2" />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('parties.index') }}" class="btn-ghost" />
                <x-button
                    label="{{ $record ? 'ذخیره تغییرات' : 'ساخت طرف‌حساب' }}"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
