<div>
    <x-header title="انبار جدید" subtitle="ثبت یک انبار فیزیکی مشترک هلدینگ" separator />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام انبار" wire:model="name" :icon="theme_icon('warehouse')" required />

            <x-textarea label="آدرس" wire:model="address" rows="3" />

            <x-checkbox label="فعال" wire:model="is_active" />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('inventory.warehouses.index') }}" />
                <x-button label="ثبت انبار" icon="{{ theme_icon('add') }}" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
