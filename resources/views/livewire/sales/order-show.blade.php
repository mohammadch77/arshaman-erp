<div>
    <x-header title="سفارش #{{ \App\Support\Farsi::toDigits($order->order_number) }}" subtitle="جزئیات و وضعیت سفارش" separator>
        <x-slot:actions>
            <x-button label="بازگشت به فهرست" :icon="theme_icon('back')" link="{{ route('sales.orders.index') }}" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <x-card shadow class="lg:col-span-2">
            <x-slot:title>اقلام سفارش</x-slot:title>

            <x-table
                :headers="[
                    ['key' => 'product', 'label' => 'محصول'],
                    ['key' => 'fulfillment_type', 'label' => 'نوع تحویل'],
                    ['key' => 'quantity', 'label' => 'تعداد'],
                    ['key' => 'unit_sale_price_amount', 'label' => 'قیمت واحد'],
                    ['key' => 'line_total_amount', 'label' => 'جمع'],
                ]"
                :rows="$order->lines"
            >
                @scope('cell_product', $line)
                    {{ $line->product?->name ?? '—' }}
                @endscope

                @scope('cell_fulfillment_type', $line)
                    <x-badge value="{{ $line->fulfillment_type->label() }}" class="badge-neutral" />
                @endscope

                @scope('cell_quantity', $line)
                    {{ \App\Support\Farsi::toDigits($line->quantity) }}
                @endscope

                @scope('cell_unit_sale_price_amount', $line)
                    {{ \App\Support\Farsi::toToman($line->unit_sale_price_amount) }}
                @endscope

                @scope('cell_line_total_amount', $line)
                    {{ \App\Support\Farsi::toToman($line->line_total_amount) }}
                @endscope
            </x-table>
        </x-card>

        <div class="flex flex-col gap-4">
            <x-card shadow>
                <x-slot:title>وضعیت فعلی</x-slot:title>

                <div class="flex flex-col gap-3">
                    <x-badge value="{{ $order->order_status->label() }}" class="badge-primary badge-lg" />

                    <div class="text-sm text-base-content/70">
                        مشتری: {{ $order->party?->name ?? '—' }}
                    </div>
                    <div class="text-sm text-base-content/70">
                        مبلغ کل: {{ \App\Support\Farsi::toToman($order->total_amount) }}
                    </div>
                    <div class="text-sm text-base-content/70">
                        تاریخ ثبت: {{ \App\Support\Jalali::toDisplay($order->created_at) }}
                    </div>
                </div>
            </x-card>

            @if (count($this->allowedTransitions) > 0)
                <x-card shadow>
                    <x-slot:title>تغییر وضعیت</x-slot:title>

                    <div class="flex flex-col gap-2">
                        @foreach ($this->allowedTransitions as $transition)
                            <x-button
                                label="{{ $transition['label'] }}"
                                icon="{{ $transition['icon'] }}"
                                wire:click="transition('{{ $transition['status'] }}')"
                                wire:confirm="وضعیت سفارش به «{{ $transition['label'] }}» تغییر کند؟"
                                class="btn-outline"
                            />
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</div>
