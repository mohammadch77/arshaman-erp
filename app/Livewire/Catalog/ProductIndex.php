<?php

namespace App\Livewire\Catalog;

use App\Modules\Catalog\Enums\FulfillmentType;
use App\Modules\Catalog\Enums\UnitOfMeasure;
use App\Modules\Catalog\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $fulfillmentType = '';

    public string $unitOfMeasure = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFulfillmentType(): void
    {
        $this->resetPage();
    }

    public function updatedUnitOfMeasure(): void
    {
        $this->resetPage();
    }

    public function getFulfillmentTypeOptionsProperty(): array
    {
        return array_map(fn (FulfillmentType $case) => ['id' => $case->value, 'name' => $case->label()], FulfillmentType::cases());
    }

    public function getUnitOfMeasureOptionsProperty(): array
    {
        return array_map(fn (UnitOfMeasure $case) => ['id' => $case->value, 'name' => $case->label()], UnitOfMeasure::cases());
    }

    public function getProductsProperty()
    {
        return Product::query()
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->fulfillmentType, fn ($query) => $query->where('fulfillment_type', $this->fulfillmentType))
            ->when($this->unitOfMeasure, fn ($query) => $query->where('unit_of_measure', $this->unitOfMeasure))
            ->orderBy('name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.catalog.product-index', [
            'products' => $this->products,
        ]);
    }
}
