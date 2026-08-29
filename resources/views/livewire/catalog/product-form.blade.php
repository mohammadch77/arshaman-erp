<div>
    <x-header
        title="{{ $record ? 'ویرایش محصول' : 'محصول جدید' }}"
        subtitle="اطلاعات محصول یا خدمت"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام محصول" wire:model="name" :icon="theme_icon('product')" required />

            <x-input label="کد کالا (SKU) — اختیاری" wire:model="sku" :icon="theme_icon('sku')" />

            <x-select
                label="نوع تحویل"
                wire:model="fulfillment_type"
                :options="$this->fulfillmentTypeOptions"
                option-value="id"
                option-label="name"
                required
            />

            <x-select
                label="واحد اندازه‌گیری"
                wire:model="unit_of_measure"
                :options="$this->unitOfMeasureOptions"
                option-value="id"
                option-label="name"
                required
            />

            <x-input label="قیمت فروش" wire:model="sale_price" :icon="theme_icon('money')" required />

            <div>
                <x-input label="بهای تمام‌شده" wire:model.live="cost_price" :icon="theme_icon('money')" />
                @if($this->showsCostWarning)
                    <div class="mt-2">
                        <x-badge value="بهای تمام‌شده نامشخص — سود این محصول قابل محاسبه نیست" class="badge-warning" :icon="theme_icon('warning')" />
                    </div>
                @endif
            </div>

            <x-select
                label="ارز (خالی = تومان)"
                wire:model="currency_id"
                :options="$this->currencyOptions"
                option-value="id"
                option-label="name"
                placeholder="تومان (پیش‌فرض)"
                placeholder-value=""
            />

            <x-input label="شناسه محصول ووکامرس" wire:model="woocommerce_product_id" :icon="theme_icon('woocommerce')" />

            <x-input label="نقطه سفارش مجدد (خالی = بدون هشدار موجودی کم)" wire:model="reorder_point" :icon="theme_icon('warehouse')" />

            @if($record)
                <x-checkbox label="فعال" wire:model="is_active" />
            @endif

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('products.index') }}" class="btn-ghost" />
                <x-button
                    label="{{ $record ? 'ذخیره تغییرات' : 'ساخت محصول' }}"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
