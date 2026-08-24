<?php

namespace App\Modules\Shipping\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Sales\Actions\TransitionOrderStatus;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Shipping\Enums\ShipmentStatus;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class MarkDelivered
{
    public function handle(Shipment $shipment, User $actor): Shipment
    {
        Gate::forUser($actor)->authorize('manage', [Shipment::class, $shipment->owner_company_id]);

        if ($shipment->status !== ShipmentStatus::Shipped) {
            throw new InvalidArgumentException('فقط مرسوله ارسال‌شده قابل ثبت تحویل است.');
        }

        return DB::transaction(function () use ($shipment, $actor) {
            $order = Order::withoutGlobalScopes()
                ->where('id', $shipment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $shipment->update([
                'status' => ShipmentStatus::Delivered,
                'delivered_at' => now(),
            ]);

            app(TransitionOrderStatus::class)->handle($order, OrderStatus::Delivered, $actor, 'تحویل مرسوله به مشتری');

            activity()
                ->causedBy($actor)
                ->performedOn($shipment)
                ->log('ثبت تحویل مرسوله');

            return $shipment->refresh();
        });
    }
}
