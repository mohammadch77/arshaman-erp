<div>
    <x-header title="سفارش‌ها" subtitle="فهرست سفارش‌های ثبت‌شده" separator>
        <x-slot:actions>
            <x-select
                wire:model.live="orderStatus"
                :options="$this->orderStatusOptions"
                option-value="id"
                option-label="name"
                placeholder="همه وضعیت‌ها"
                placeholder-value=""
            />
            <x-button label="سفارش دستی جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('sales.orders.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'order_number', 'label' => 'شماره سفارش'],
                ['key' => 'party', 'label' => 'مشتری'],
                ['key' => 'order_status', 'label' => 'وضعیت'],
                ['key' => 'source', 'label' => 'منبع'],
                ['key' => 'total_amount', 'label' => 'مبلغ کل'],
                ['key' => 'created_at', 'label' => 'تاریخ ثبت'],
            ]"
            :rows="$orders"
            with-pagination
        >
            @scope('cell_order_number', $order)
                <a href="{{ route('sales.orders.show', $order->id) }}" class="link link-primary">#{{ $order->order_number }}</a>
            @endscope

            @scope('cell_party', $order)
                {{ $order->party?->name ?? '—' }}
            @endscope

            @scope('cell_order_status', $order)
                <x-badge value="{{ $order->order_status->label() }}" class="badge-neutral" />
            @endscope

            @scope('cell_source', $order)
                {{ $order->source->label() }}
            @endscope

            @scope('cell_total_amount', $order)
                {{ \App\Support\Farsi::toToman($order->total_amount) }}
            @endscope

            @scope('cell_created_at', $order)
                {{ \App\Support\Jalali::toDisplay($order->created_at) }}
            @endscope
        </x-table>
    </x-card>
</div>
