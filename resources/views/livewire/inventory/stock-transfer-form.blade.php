<div>
    <x-header
        title="جابجایی موجودی بین انبارها"
        subtitle="کاهش خودکار انبار مبدأ، افزایش خودکار انبار مقصد — هر جابجایی دو ردیف دفترچه حرکت می‌سازد"
        separator
    />

    <x-card shadow class="max-w-2xl mb-6">
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
                label="انبار مبدأ"
                wire:model.live="from_warehouse_id"
                :options="$this->warehouseOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('warehouse')"
                required
            />

            @if ($this->fromStockQuantity !== null)
                <div class="text-sm opacity-60">
                    موجودی فعلی در انبار مبدأ: {{ $this->fromStockQuantity }}
                </div>
            @endif

            <x-select
                label="انبار مقصد"
                wire:model="to_warehouse_id"
                :options="$this->warehouseOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('warehouse')"
                required
            />

            <x-input label="تعداد" wire:model="quantity" :icon="theme_icon('stock-transfer')" required />

            <x-textarea
                label="یادداشت (اختیاری)"
                wire:model="note"
                :icon="theme_icon('note')"
            />

            <x-slot:actions>
                <x-button
                    label="ثبت جابجایی"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('stock-transfer')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>

    <x-card shadow title="تاریخچه جابجایی‌ها">
        <x-table
            :headers="[
                ['key' => 'created_at', 'label' => 'تاریخ'],
                ['key' => 'product', 'label' => 'محصول'],
                ['key' => 'from_warehouse', 'label' => 'از انبار'],
                ['key' => 'to_warehouse', 'label' => 'به انبار'],
                ['key' => 'quantity', 'label' => 'تعداد'],
                ['key' => 'note', 'label' => 'یادداشت'],
                ['key' => 'created_by', 'label' => 'ثبت‌کننده'],
            ]"
            :rows="$transfers"
            with-pagination
        >
            @scope('cell_created_at', $transfer)
                {{ \App\Support\Jalali::toDisplayDateTime($transfer->created_at) }}
            @endscope

            @scope('cell_product', $transfer)
                {{ $transfer->product->name }}
            @endscope

            @scope('cell_from_warehouse', $transfer)
                {{ $transfer->fromWarehouse->name }}
            @endscope

            @scope('cell_to_warehouse', $transfer)
                {{ $transfer->toWarehouse->name }}
            @endscope

            @scope('cell_quantity', $transfer)
                {{ \App\Support\Farsi::formatQuantity($transfer->quantity, $transfer->product->unit_of_measure) }}
            @endscope

            @scope('cell_note', $transfer)
                {{ $transfer->note ?: '—' }}
            @endscope

            @scope('cell_created_by', $transfer)
                {{ $transfer->createdBy?->full_name ?? '—' }}
            @endscope
        </x-table>
    </x-card>
</div>
