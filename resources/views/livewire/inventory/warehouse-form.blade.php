<div>
    <x-header
        title="{{ $record ? 'ویرایش انبار' : 'انبار جدید' }}"
        subtitle="{{ $record ? 'ویرایش اطلاعات انبار فیزیکی مشترک هلدینگ' : 'ثبت یک انبار فیزیکی مشترک هلدینگ' }}"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام انبار" wire:model="name" :icon="theme_icon('warehouse')" required />

            <x-textarea label="آدرس" wire:model="address" rows="3" />

            @if($record)
                <x-checkbox label="فعال" wire:model="is_active" />
            @endif

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('inventory.warehouses.index') }}" />
                <x-button label="{{ $record ? 'ذخیره تغییرات' : 'ثبت انبار' }}" icon="{{ theme_icon($record ? 'save' : 'add') }}" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
