<?php

namespace App\Livewire\Sales;

use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class OrderIndex extends Component
{
    use WithPagination;

    public string $orderStatus = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    public function updatedOrderStatus(): void
    {
        $this->resetPage();
    }

    public function getOrderStatusOptionsProperty(): array
    {
        return array_map(fn (OrderStatus $case) => ['id' => $case->value, 'name' => $case->label()], OrderStatus::cases());
    }

    public function getOrdersProperty()
    {
        return Order::query()
            ->with(['party', 'currency'])
            ->when($this->orderStatus, fn ($query) => $query->where('order_status', $this->orderStatus))
            ->orderByDesc('order_number')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.sales.order-index', [
            'orders' => $this->orders,
        ]);
    }
}
