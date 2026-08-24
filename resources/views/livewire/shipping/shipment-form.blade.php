<div>
    <x-header title="ارسال سفارش #{{ \App\Support\Farsi::toDigits($order->order_number) }}" subtitle="بسته‌بندی، کد رهگیری و تحویل" separator>
        <x-slot:actions>
            <x-button label="بازگشت به فهرست" :icon="theme_icon('back')" link="{{ route('shipping.orders.index') }}" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 flex flex-col gap-4">
            @if (! $shipment || $shipment->status->value === 'pending')
                <x-card shadow>
                    <x-slot:title>بسته‌بندی سفارش</x-slot:title>

                    <div class="flex flex-col gap-4">
                        <x-input label="هزینه ارسال (تومان)" wire:model="shippingCostAmount" :icon="theme_icon('money')" />
                        <x-button label="ثبت بسته‌بندی" :icon="theme_icon('pack')" wire:click="pack" class="btn-primary" />
                    </div>
                </x-card>
            @elseif ($shipment->status->value === 'packed')
                <x-card shadow>
                    <x-slot:title>ثبت کد رهگیری</x-slot:title>

                    <div class="flex flex-col gap-4">
                        <x-input label="شرکت حمل" wire:model="carrier" disabled :icon="theme_icon('shipping')" />
                        <x-input label="کد رهگیری" wire:model="trackingCode" :icon="theme_icon('tracking')" />
                        <x-button label="ثبت کد رهگیری و ارسال" :icon="theme_icon('send')" wire:click="assignTracking" class="btn-primary" />
                    </div>
                </x-card>
            @elseif ($shipment->status->value === 'shipped')
                <x-card shadow>
                    <x-slot:title>ارسال شده — در انتظار تحویل</x-slot:title>

                    <div class="flex flex-col gap-3">
                        <div class="text-sm text-base-content/70">کد رهگیری: {{ $shipment->tracking_code }}</div>
                        <x-button
                            label="ثبت تحویل به مشتری"
                            :icon="theme_icon('approve')"
                            wire:click="markDelivered"
                            wire:confirm="تحویل این مرسوله به مشتری ثبت شود؟"
                            class="btn-primary"
                        />
                    </div>
                </x-card>
            @else
                <x-card shadow>
                    <x-slot:title>تحویل‌شده</x-slot:title>

                    <x-badge value="تحویل به مشتری تکمیل شد" class="badge-success badge-lg" />
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-4">
            <x-card shadow>
                <x-slot:title>خلاصه سفارش</x-slot:title>

                <div class="flex flex-col gap-3">
                    <x-badge value="{{ $order->order_status->label() }}" class="badge-neutral" />

                    <div class="text-sm text-base-content/70">مشتری: {{ $order->party?->name ?? '—' }}</div>
                    <div class="text-sm text-base-content/70">جمع کل: {{ \App\Support\Farsi::toToman($order->total_amount) }}</div>
                    <div class="text-sm text-base-content/70">هزینه ارسال: {{ \App\Support\Farsi::toToman($order->shipping_amount) }}</div>

                    @if ($shipment)
                        <div class="text-sm text-base-content/70">شرکت حمل: {{ $shipment->carrier }}</div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</div>
