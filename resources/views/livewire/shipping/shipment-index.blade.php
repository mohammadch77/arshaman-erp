<div>
    <x-header title="ارسال و حمل" subtitle="فهرست سفارش‌های در چرخه‌ی بسته‌بندی و ارسال" separator />

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'order_number', 'label' => 'شماره سفارش'],
                ['key' => 'party', 'label' => 'مشتری'],
                ['key' => 'order_status', 'label' => 'وضعیت سفارش'],
                ['key' => 'shipment_status', 'label' => 'وضعیت ارسال'],
                ['key' => 'tracking_code', 'label' => 'کد رهگیری'],
                ['key' => 'actions', 'label' => ''],
            ]"
            :rows="$orders"
            with-pagination
        >
            @scope('cell_order_number', $order)
                #{{ \App\Support\Farsi::toDigits($order->order_number) }}
            @endscope

            @scope('cell_party', $order)
                {{ $order->party?->name ?? '—' }}
            @endscope

            @scope('cell_order_status', $order)
                <x-badge value="{{ $order->order_status->label() }}" class="badge-neutral" />
            @endscope

            @scope('cell_shipment_status', $order, $shipmentsByOrderId)
                @php($shipment = $shipmentsByOrderId[$order->id] ?? null)
                @if ($shipment)
                    <x-badge value="{{ $shipment->status->label() }}" class="badge-primary" />
                @else
                    <x-badge value="در انتظار بسته‌بندی" class="badge-ghost" />
                @endif
            @endscope

            @scope('cell_tracking_code', $order, $shipmentsByOrderId)
                {{ ($shipmentsByOrderId[$order->id] ?? null)?->tracking_code ?? '—' }}
            @endscope

            @scope('cell_actions', $order)
                <x-button label="مدیریت ارسال" :icon="theme_icon('shipping')" link="{{ route('shipping.orders.show', $order->id) }}" class="btn-outline btn-sm" />
            @endscope
        </x-table>
    </x-card>
</div>
