<?php

namespace App\Livewire\Shipping;

use App\Modules\Sales\Models\Order;
use App\Modules\Shipping\Actions\AssignTrackingCode;
use App\Modules\Shipping\Actions\MarkDelivered;
use App\Modules\Shipping\Actions\PackOrder;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Mary\Traits\Toast;

class ShipmentForm extends Component
{
    use Toast;

    public Order $order;

    public ?Shipment $shipment = null;

    public string $shippingCostAmount = '0';

    public string $trackingCode = '';

    public string $carrier = 'tipax';

    /**
     * پارامتر عمداً orderId نام‌گذاری شده، نه order — همان باگ تکراری bind
     * خودکار Livewire مستندشده در چند ماژول پروژه (همنام‌بودن با یک property
     * تایپ‌شده باعث می‌شود Livewire قبل از mount() مقدار خام route را مستقیم
     * رویش بنشاند).
     */
    public function mount(string $orderId): void
    {
        $this->order = Order::with('lines')->findOrFail($orderId);
        $this->authorize('viewAny', Shipment::class);

        $this->shipment = Shipment::withoutGlobalScopes()
            ->where('order_id', $this->order->id)
            ->first();

        if ($this->shipment !== null) {
            $this->shippingCostAmount = (string) $this->shipment->shipping_cost_amount;
            $this->trackingCode = (string) $this->shipment->tracking_code;
            $this->carrier = $this->shipment->carrier;
        }
    }

    public function pack(PackOrder $action): void
    {
        try {
            $this->shipment = $action->handle($this->order, $this->shippingCostAmount, auth()->user());
            $this->order->refresh();
            $this->success('سفارش بسته‌بندی شد.');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        }
    }

    public function assignTracking(AssignTrackingCode $action): void
    {
        if (trim($this->trackingCode) === '') {
            $this->error('کد رهگیری را وارد کنید.');

            return;
        }

        try {
            $this->shipment = $action->handle($this->shipment, $this->trackingCode, auth()->user());
            $this->order->refresh();
            $this->success('کد رهگیری ثبت و سفارش ارسال شد.');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());
        }
    }

    public function markDelivered(MarkDelivered $action): void
    {
        try {
            $this->shipment = $action->handle($this->shipment, auth()->user());
            $this->order->refresh();
            $this->success('تحویل مرسوله ثبت شد.');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());
        }
    }

    public function render()
    {
        return view('livewire.shipping.shipment-form');
    }
}
