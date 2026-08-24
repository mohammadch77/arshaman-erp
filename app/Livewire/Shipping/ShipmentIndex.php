<?php

namespace App\Livewire\Shipping;

use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Shipping\Models\Shipment;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * فهرست سفارش‌های در چرخه‌ی ارسال (preparing تا delivered). چون Order در
 * ماژول Sales رابطه‌ای به Shipment (ماژول Shipping) ندارد (قانون وابستگی
 * بند ۴ CLAUDE.md)، مرسوله‌ها جدا کوئری و با order_id به سفارش‌ها map
 * می‌شوند — نه یک رابطه Eloquent مستقیم.
 */
class ShipmentIndex extends Component
{
    use WithPagination;

    private const RELEVANT_STATUSES = [
        OrderStatus::Preparing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Shipment::class);
    }

    public function getOrdersProperty()
    {
        return Order::query()
            ->with('party')
            ->whereHas('lines', fn ($query) => $query->where('fulfillment_type', 'physical'))
            ->whereIn('order_status', array_map(fn (OrderStatus $status) => $status->value, self::RELEVANT_STATUSES))
            ->orderByDesc('order_number')
            ->paginate(15);
    }

    public function getShipmentsByOrderIdProperty(): array
    {
        $orderIds = $this->orders->pluck('id')->all();

        return Shipment::query()
            ->whereIn('order_id', $orderIds)
            ->get()
            ->keyBy('order_id')
            ->all();
    }

    public function render()
    {
        return view('livewire.shipping.shipment-index', [
            'orders' => $this->orders,
            'shipmentsByOrderId' => $this->shipmentsByOrderId,
        ]);
    }
}
