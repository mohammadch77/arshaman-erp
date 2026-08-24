<?php

namespace App\Modules\Shipping\Actions;

use App\Modules\CRM\Services\NotificationChannel;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Actions\TransitionOrderStatus;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Shipping\Enums\ShipmentStatus;
use App\Modules\Shipping\Models\Shipment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * ثبت کد رهگیری = لحظه‌ای که سفارش واقعاً ارسال می‌شود. هزینه ارسالِ همان
 * Shipment اینجا و فقط اینجا روی order.shipping_amount/total_amount نوشته
 * می‌شود — قبل از فراخوانی TransitionOrderStatus به shipped، یعنی قبل از
 * قفل مالی که فقط از delivered/closed شروع می‌شود (Order::LOCKING_STATUSES).
 */
class AssignTrackingCode
{
    public function handle(Shipment $shipment, string $trackingCode, User $actor): Shipment
    {
        Gate::forUser($actor)->authorize('manage', [Shipment::class, $shipment->owner_company_id]);

        if ($shipment->status !== ShipmentStatus::Packed) {
            throw new InvalidArgumentException('فقط مرسوله بسته‌بندی‌شده قابل ثبت کد رهگیری است.');
        }

        return DB::transaction(function () use ($shipment, $trackingCode, $actor) {
            $order = Order::withoutGlobalScopes()
                ->where('id', $shipment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $newTotalAmount = Money::round(Money::add((string) $order->subtotal_amount, (string) $shipment->shipping_cost_amount));

            $order->update([
                'shipping_amount' => $shipment->shipping_cost_amount,
                'total_amount' => $newTotalAmount,
            ]);

            $shipment->update([
                'tracking_code' => $trackingCode,
                'status' => ShipmentStatus::Shipped,
                'shipped_at' => now(),
            ]);

            app(TransitionOrderStatus::class)->handle($order, OrderStatus::Shipped, $actor, 'ثبت کد رهگیری مرسوله');

            $this->notifyCustomer($order, $trackingCode);

            activity()
                ->causedBy($actor)
                ->performedOn($shipment)
                ->withProperties(['tracking_code' => $trackingCode])
                ->log('ثبت کد رهگیری');

            return $shipment->refresh();
        });
    }

    private function notifyCustomer(Order $order, string $trackingCode): void
    {
        $party = $order->party;
        $target = $party?->phone ?? $party?->email;

        if ($target === null) {
            return;
        }

        app(NotificationChannel::class)->send(
            'sms',
            $target,
            sprintf('سفارش شماره %d شما ارسال شد. کد رهگیری: %s', $order->order_number, $trackingCode),
        );
    }
}
