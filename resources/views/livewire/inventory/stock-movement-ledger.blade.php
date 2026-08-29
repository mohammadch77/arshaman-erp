<div>
    <x-header
        title="دفترچه حرکت موجودی"
        subtitle="{{ $stock->product->name }} — {{ $stock->warehouse->name }}"
        separator
    >
        <x-slot:actions>
            <x-button label="بازگشت" link="{{ route('inventory.stock.index') }}" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    @if ($stock->isBelowReorderPoint())
        <x-alert title="موجودی این کالا زیر نقطه سفارش مجدد است" class="alert-warning mb-4" :icon="theme_icon('warning')" />
    @endif

    <x-card shadow>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div>
                <div class="text-sm opacity-60">موجودی فعلی</div>
                <div class="text-lg font-semibold">{{ \App\Support\Farsi::formatQuantity($stock->quantity_on_hand, $stock->product->unit_of_measure) }}</div>
            </div>
            <div>
                <div class="text-sm opacity-60">میانگین موزون بهای واحد</div>
                <div class="text-lg font-semibold">
                    {{ $stock->average_cost !== null ? \App\Support\Farsi::toMoney((string) $stock->average_cost, $stock->product->currency) : 'نامشخص' }}
                </div>
            </div>
        </div>

        <x-table
            :headers="[
                ['key' => 'occurred_at', 'label' => 'تاریخ'],
                ['key' => 'movement_type', 'label' => 'نوع حرکت'],
                ['key' => 'quantity', 'label' => 'تعداد'],
                ['key' => 'unit_cost', 'label' => 'بهای واحد'],
                ['key' => 'reference_note', 'label' => 'یادداشت'],
                ['key' => 'created_by', 'label' => 'ثبت‌کننده'],
            ]"
            :rows="$movements"
            with-pagination
        >
            @scope('cell_occurred_at', $movement)
                {{ \App\Support\Jalali::toDisplayDateTime($movement->occurred_at) }}
            @endscope

            @scope('cell_movement_type', $movement)
                <x-badge
                    :value="$movement->movement_type->label()"
                    class="{{ $movement->movement_type->direction() === 'in' ? 'badge-success' : 'badge-error' }}"
                />
            @endscope

            @scope('cell_quantity', $movement, $stock)
                {{ \App\Support\Farsi::formatQuantity($movement->quantity, $stock->product->unit_of_measure) }}
            @endscope

            @scope('cell_unit_cost', $movement, $stock)
                {{ $movement->unit_cost !== null ? \App\Support\Farsi::toMoney((string) $movement->unit_cost, $stock->product->currency) : '—' }}
            @endscope

            @scope('cell_reference_note', $movement)
                {{ $movement->reference_note ?: '—' }}
            @endscope

            @scope('cell_created_by', $movement)
                {{ $movement->createdBy?->full_name ?? '—' }}
            @endscope
        </x-table>
    </x-card>
</div>
