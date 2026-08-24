<div>
    <x-header title="ثبت سفارش دستی" subtitle="سفارش‌های ثبت‌شده از اینستاگرام/تلگرام/سایر شبکه‌ها" separator />

    <x-card shadow class="max-w-3xl">
        <x-form wire:submit="save" class="gap-5">
            <x-select
                label="مشتری"
                wire:model="party_id"
                :options="$this->partyOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('party')"
                required
            />

            <x-select
                label="منبع سفارش"
                wire:model="source"
                :options="$this->sourceOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('order')"
                required
            />

            <x-input
                label="شناسه خارجی (اختیاری)"
                wire:model="external_order_id"
                hint="اگر این سفارش قبلاً از جای دیگر ثبت شده و شناسه‌ای دارد"
            />

            <div class="divider">اقلام سفارش</div>

            @foreach ($lines as $index => $line)
                <div class="flex gap-3 items-end" wire:key="order-line-{{ $index }}">
                    <div class="flex-1">
                        <x-select
                            label="محصول"
                            wire:model="lines.{{ $index }}.product_id"
                            :options="$this->productOptions"
                            option-value="id"
                            option-label="name"
                            :icon="theme_icon('product')"
                            required
                        />
                    </div>
                    <div class="w-32">
                        <x-input label="تعداد" wire:model="lines.{{ $index }}.quantity" required />
                    </div>
                    <x-button
                        :icon="theme_icon('delete')"
                        class="btn-circle btn-ghost btn-sm mb-2"
                        wire:click="removeLine({{ $index }})"
                        type="button"
                    />
                </div>
            @endforeach

            <x-button label="افزودن قلم" :icon="theme_icon('add')" class="btn-ghost btn-sm w-fit" wire:click="addLine" type="button" />

            <div class="divider"></div>

            <x-input
                label="هزینه ارسال (تومان)"
                wire:model="shipping_amount"
                :icon="theme_icon('shipping')"
            />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('sales.orders.index') }}" class="btn-ghost" />
                <x-button
                    label="ثبت سفارش"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
