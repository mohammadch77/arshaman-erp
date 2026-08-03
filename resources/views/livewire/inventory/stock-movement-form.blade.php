<div>
    <x-header
        title="{{ $movementType === 'out' ? 'خروج کالا' : 'دریافت کالا' }}"
        subtitle="ثبت حرکت انبار — موجودی فقط از همین مسیر تغییر می‌کند"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-select
                label="محصول"
                wire:model="product_id"
                :options="$this->productOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('product')"
                required
            />

            <x-select
                label="انبار"
                wire:model="warehouse_id"
                :options="$this->warehouseOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('warehouse')"
                required
            />

            <x-input label="تعداد" wire:model="quantity" :icon="theme_icon('inventory')" required />

            <x-input label="دلیل (اختیاری)" wire:model="reason" :icon="theme_icon('note')" />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('inventory.stock.index') }}" class="btn-ghost" />
                <x-button
                    label="{{ $movementType === 'out' ? 'ثبت خروج' : 'ثبت دریافت' }}"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
