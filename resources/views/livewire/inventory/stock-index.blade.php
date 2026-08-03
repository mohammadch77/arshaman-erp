<div>
    <x-header title="موجودی انبار" subtitle="موجودی محصولات شرکت جاری به تفکیک انبار" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام محصول..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="دریافت کالا" :icon="theme_icon('stock-in')" class="btn-primary" link="{{ route('inventory.receive') }}" responsive />
            <x-button label="خروج کالا" :icon="theme_icon('stock-out')" class="btn-secondary" link="{{ route('inventory.issue') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'product_name', 'label' => 'محصول'],
                ['key' => 'warehouse_name', 'label' => 'انبار'],
                ['key' => 'quantity', 'label' => 'موجودی'],
                ['key' => 'reorder_status', 'label' => 'وضعیت'],
            ]"
            :rows="$stocks"
            with-pagination
        >
            @scope('cell_product_name', $stock)
                {{ $stock->product->name }}
            @endscope

            @scope('cell_warehouse_name', $stock)
                {{ $stock->warehouse->name }}
            @endscope

            @scope('cell_quantity', $stock)
                {{ \App\Support\Farsi::toDigits($stock->quantity) }}
            @endscope

            @scope('cell_reorder_status', $stock)
                @if($stock->isBelowReorderPoint())
                    <x-badge value="زیر نقطه سفارش" class="badge-warning" :icon="theme_icon('warning')" />
                @else
                    <x-badge value="نرمال" class="badge-success" />
                @endif
            @endscope
        </x-table>
    </x-card>
</div>
