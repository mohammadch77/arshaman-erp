<?php

namespace App\Livewire\Inventory;

use App\Modules\Inventory\Models\Stock;
use Livewire\Component;

class LowStockReport extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Stock::class);
    }

    public function getStocksProperty()
    {
        // آستانه هر ردیف یا از خودِ stock (اختصاصی انبار) یا از products.reorder_point
        // (پیش‌فرض شرکت) می‌آید — Stock::isBelowReorderPoint() این fallback را حمل
        // می‌کند، پس مقایسه در سطح PHP انجام می‌شود نه یک whereColumn ساده.
        return Stock::query()
            ->with(['product', 'warehouse'])
            ->get()
            ->filter(fn (Stock $stock) => $stock->isBelowReorderPoint())
            ->sortBy('quantity_on_hand')
            ->values();
    }

    public function render()
    {
        return view('livewire.inventory.low-stock-report', [
            'stocks' => $this->stocks,
        ]);
    }
}
