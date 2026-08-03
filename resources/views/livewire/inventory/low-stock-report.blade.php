<div>
    <x-header title="کالاهای زیر نقطه سفارش" subtitle="محصولاتی که موجودی‌شان از نقطه سفارش کمتر است" separator />

    <x-card shadow>
        @if($stocks->isEmpty())
            <x-alert title="فعلاً هیچ کالایی زیر نقطه سفارش نیست." class="alert-success" :icon="theme_icon('approve')" />
        @else
            <x-table
                :headers="[
                    ['key' => 'product_name', 'label' => 'محصول'],
                    ['key' => 'warehouse_name', 'label' => 'انبار'],
                    ['key' => 'quantity', 'label' => 'موجودی فعلی'],
                    ['key' => 'reorder_point', 'label' => 'نقطه سفارش'],
                ]"
                :rows="$stocks"
            >
                @scope('cell_product_name', $stock)
                    {{ $stock->product->name }}
                @endscope

                @scope('cell_warehouse_name', $stock)
                    {{ $stock->warehouse->name }}
                @endscope

                @scope('cell_quantity', $stock)
                    <x-badge value="{{ \App\Support\Farsi::toDigits($stock->quantity) }}" class="badge-warning" :icon="theme_icon('warning')" />
                @endscope

                @scope('cell_reorder_point', $stock)
                    {{ \App\Support\Farsi::toDigits($stock->product->reorder_point) }}
                @endscope
            </x-table>
        @endif
    </x-card>
</div>
