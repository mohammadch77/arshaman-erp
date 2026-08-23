<div>
    <x-header
        title="ثبت حرکت موجودی"
        subtitle="موجودی فقط از همین مسیر تغییر می‌کند — هر ثبت یک ردیف دفترچه حرکت می‌سازد"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-select
                label="نوع حرکت"
                wire:model.live="movementType"
                :options="$this->movementTypeOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon($this->isInbound ? 'stock-in' : 'stock-out')"
                required
            />

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

            @if ($this->isPurchase)
                <x-input
                    label="بهای واحد (تومان، اختیاری)"
                    wire:model="unit_cost"
                    hint="اگر پر شود، میانگین موزون بهای این محصول در همین انبار به‌روزرسانی می‌شود"
                />
            @endif

            <x-textarea
                label="یادداشت / دلیل{{ $this->isAdjustment ? ' (الزامی)' : ' (اختیاری)' }}"
                wire:model="reference_note"
                :icon="theme_icon('note')"
                :required="$this->isAdjustment"
            />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('inventory.stock.index') }}" class="btn-ghost" />
                <x-button
                    label="ثبت حرکت"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
