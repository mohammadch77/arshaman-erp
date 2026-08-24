<?php

namespace App\Modules\Shipping\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\OrderStateMachine;
use App\Modules\Shipping\Enums\ShipmentStatus;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * قدم اول ارسال: بسته‌بندی سفارش + ثبت هزینه ارسال روی Shipment. عمداً هنوز
 * چیزی روی order (shipping_amount/total_amount) نمی‌نویسد — آن فقط در
 * AssignTrackingCode (لحظه‌ی رسیدن به shipped) اتفاق می‌افتد، طبق تصمیم
 * مستند نقشه این Session: یک نقطه نوشتن، نه دو.
 */
class PackOrder
{
    public function handle(Order $order, string $shippingCostAmount, User $actor): Shipment
    {
        Gate::forUser($actor)->authorize('manage', [Shipment::class, $order->owner_company_id]);

        $stateMachine = app(OrderStateMachine::class);

        if (! $stateMachine->hasPhysicalLine($order)) {
            throw new InvalidArgumentException('سفارش بدون قلم فیزیکی نیاز به بسته‌بندی/ارسال ندارد.');
        }

        if ($order->order_status !== OrderStatus::Preparing) {
            throw new InvalidArgumentException('فقط سفارش در حال آماده‌سازی قابل بسته‌بندی است.');
        }

        return DB::transaction(function () use ($order, $shippingCostAmount, $actor) {
            $shipment = Shipment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if ($shipment !== null && $shipment->status !== ShipmentStatus::Pending) {
                throw new InvalidArgumentException('این سفارش قبلاً بسته‌بندی شده است.');
            }

            if ($shipment === null) {
                $shipment = Shipment::create([
                    'owner_company_id' => $order->owner_company_id,
                    'order_id' => $order->id,
                    'status' => ShipmentStatus::Packed,
                    'shipping_cost_amount' => $shippingCostAmount,
                    'created_by_user_id' => $actor->id,
                ]);
            } else {
                $shipment->update([
                    'status' => ShipmentStatus::Packed,
                    'shipping_cost_amount' => $shippingCostAmount,
                ]);
            }

            activity()
                ->causedBy($actor)
                ->performedOn($shipment)
                ->withProperties(['shipping_cost_amount' => $shippingCostAmount])
                ->log('بسته‌بندی سفارش');

            return $shipment->refresh();
        });
    }
}
