<div>
    <x-header title="انبارها" subtitle="انبارهای فیزیکی و موجودی هر شرکت مالک در هرکدام" separator>
        @if($this->canCreateWarehouse)
            <x-slot:actions>
                <x-button label="انبار جدید" link="{{ route('inventory.warehouses.create') }}" icon="{{ theme_icon('add') }}" class="btn-primary" />
            </x-slot:actions>
        @endif
    </x-header>

    @if($warehouses->isEmpty())
        <x-alert title="هنوز هیچ انباری ثبت نشده است." :icon="theme_icon('inventory')" />
    @endif

    @foreach($warehouses as $warehouse)
        @php($stocks = $stocksByWarehouse->get($warehouse->id, collect()))

        <x-card shadow class="mb-4">
            <x-slot:title>
                {{ $warehouse->name }}
                @if($warehouse->is_active)
                    <x-badge value="فعال" class="badge-success" />
                @else
                    <x-badge value="غیرفعال" class="badge-error" />
                @endif
            </x-slot:title>

            @if($this->canManageWarehouses)
                <x-slot:menu>
                    <x-button label="ویرایش" :icon="theme_icon('edit')" link="{{ route('inventory.warehouses.edit', $warehouse->id) }}" class="btn-ghost btn-sm" />
                    <x-button
                        label="{{ $warehouse->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}"
                        :icon="theme_icon($warehouse->is_active ? 'deactivate' : 'activate')"
                        wire:click="toggleActive('{{ $warehouse->id }}')"
                        wire:confirm="وضعیت فعال‌بودن این انبار تغییر کند؟"
                        class="btn-ghost btn-sm"
                    />
                </x-slot:menu>
            @endif

            @if($warehouse->address)
                <p class="text-sm text-base-content/70 mb-4">{{ $warehouse->address }}</p>
            @endif

            @if($stocks->isEmpty())
                <x-alert title="هنوز موجودی‌ای در این انبار ثبت نشده است." class="alert-info" />
            @else
                <x-table
                    :headers="[
                        ['key' => 'company_name', 'label' => 'شرکت مالک'],
                        ['key' => 'product_name', 'label' => 'محصول'],
                        ['key' => 'quantity_on_hand', 'label' => 'موجودی'],
                        ['key' => 'average_cost', 'label' => 'میانگین بهای واحد'],
                        ['key' => 'reorder_status', 'label' => 'وضعیت'],
                    ]"
                    :rows="$stocks"
                >
                    @scope('cell_company_name', $stock)
                        {{ $stock->ownerCompany?->name }}
                    @endscope

                    @scope('cell_product_name', $stock)
                        {{ $stock->product->name }}
                    @endscope

                    @scope('cell_quantity_on_hand', $stock)
                        {{ \App\Support\Farsi::formatQuantity($stock->quantity_on_hand, $stock->product->unit_of_measure) }}
                    @endscope

                    @scope('cell_average_cost', $stock)
                        @if($stock->average_cost !== null)
                            {{ \App\Support\Farsi::toMoney($stock->average_cost, $stock->product->currency) }}
                        @else
                            <span class="text-base-content/50">نامشخص</span>
                        @endif
                    @endscope

                    @scope('cell_reorder_status', $stock)
                        @if($stock->isBelowReorderPoint())
                            <x-badge value="زیر نقطه سفارش" class="badge-warning" :icon="theme_icon('warning')" />
                        @else
                            <x-badge value="نرمال" class="badge-success" />
                        @endif
                    @endscope
                </x-table>
            @endif
        </x-card>
    @endforeach
</div>
