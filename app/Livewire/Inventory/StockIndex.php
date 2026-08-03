<?php

namespace App\Livewire\Inventory;

use App\Modules\Inventory\Models\Stock;
use Livewire\Component;
use Livewire\WithPagination;

class StockIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Stock::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getStocksProperty()
    {
        return Stock::query()
            ->with(['product', 'warehouse'])
            ->when($this->search, fn ($query) => $query->whereHas(
                'product',
                fn ($productQuery) => $productQuery->where('name', 'like', "%{$this->search}%")
            ))
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.inventory.stock-index', [
            'stocks' => $this->stocks,
        ]);
    }
}
