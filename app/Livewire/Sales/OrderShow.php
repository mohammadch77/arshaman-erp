<?php

namespace App\Livewire\Sales;

use App\Modules\Sales\Actions\TransitionOrderStatus;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\OrderStateMachine;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Mary\Traits\Toast;

class OrderShow extends Component
{
    use Toast;

    public Order $order;

    /**
     * پارامتر عمداً orderId نام‌گذاری شده، نه order — همنام‌بودن با یک
     * property تایپ‌شده (public Order $order) باعث می‌شود Livewire پیش از
     * اجرای mount() مقدار خام رشته‌ای route را مستقیم رویش بنشاند و با خطای
     * type mismatch شکست بخورد (باگ تکراری مستندشده در چند ماژول پروژه).
     */
    public function mount(string $orderId): void
    {
        $this->order = Order::with('lines.product')->findOrFail($orderId);
        $this->authorize('view', $this->order);
    }

    /**
     * @return array<int, array{status: string, label: string, icon: string}>
     */
    public function getAllowedTransitionsProperty(): array
    {
        return array_map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'icon' => $this->iconFor($status),
        ], app(OrderStateMachine::class)->allowedTransitions($this->order));
    }

    private function iconFor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Paid => theme_icon('money'),
            OrderStatus::Preparing => theme_icon('stock-out'),
            OrderStatus::Shipped => theme_icon('shipping'),
            OrderStatus::Delivered, OrderStatus::DeliveredInstant => theme_icon('approve'),
            OrderStatus::Closed => theme_icon('locked'),
            OrderStatus::Cancelled => theme_icon('cancel'),
            OrderStatus::Returned => theme_icon('undo'),
            default => theme_icon('process'),
        };
    }

    public function transition(string $status, TransitionOrderStatus $action): void
    {
        try {
            $this->order = $action->handle($this->order, OrderStatus::from($status), auth()->user());
            $this->success('وضعیت سفارش با موفقیت تغییر کرد.');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());
        }
    }

    public function render()
    {
        return view('livewire.sales.order-show');
    }
}
