<div>
    <x-header title="محصولات" subtitle="فهرست محصولات و خدمات" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام محصول..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select
                wire:model.live="fulfillmentType"
                :options="$this->fulfillmentTypeOptions"
                option-value="id"
                option-label="name"
                placeholder="همه انواع تحویل"
                placeholder-value=""
            />
            <x-button label="محصول جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('products.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'name', 'label' => 'نام'],
                ['key' => 'fulfillment_type', 'label' => 'نوع تحویل'],
                ['key' => 'sale_price', 'label' => 'قیمت فروش'],
                ['key' => 'cost_price', 'label' => 'بهای تمام‌شده'],
                ['key' => 'is_active', 'label' => 'وضعیت'],
            ]"
            :rows="$products"
            with-pagination
        >
            @scope('cell_fulfillment_type', $product)
                {{ $product->fulfillment_type->label() }}
            @endscope

            @scope('cell_sale_price', $product)
                {{ \App\Support\Farsi::toToman($product->sale_price) }}
            @endscope

            @scope('cell_cost_price', $product)
                @if($product->needsCostReview())
                    <x-badge value="بهای تمام‌شده نامشخص" class="badge-warning" :icon="theme_icon('warning')" />
                @else
                    {{ \App\Support\Farsi::toToman($product->cost_price) }}
                @endif
            @endscope

            @scope('cell_is_active', $product)
                @if($product->is_active)
                    <x-badge value="فعال" class="badge-success" />
                @else
                    <x-badge value="غیرفعال" class="badge-ghost" />
                @endif
            @endscope

            @scope('actions', $product)
                <x-button
                    :icon="theme_icon('edit')"
                    tooltip-left="ویرایش"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('products.edit', $product->id) }}"
                />
            @endscope
        </x-table>
    </x-card>
</div>
