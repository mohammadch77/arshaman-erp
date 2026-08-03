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
        return Stock::query()
            ->with(['product', 'warehouse'])
            ->whereHas('product', fn ($query) => $query
                ->whereNotNull('reorder_point')
                ->whereColumn('products.reorder_point', '>', 'stocks.quantity'))
            ->orderBy('quantity')
            ->get();
    }

    public function render()
    {
        return view('livewire.inventory.low-stock-report', [
            'stocks' => $this->stocks,
        ]);
    }
}
