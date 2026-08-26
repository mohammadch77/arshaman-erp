<?php

namespace App\Livewire\Inventory;

use App\Modules\Inventory\Actions\CreateWarehouse;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Mary\Traits\Toast;

class WarehouseForm extends Component
{
    use Toast;

    public string $name = '';

    public string $address = '';

    public bool $is_active = true;

    public function mount(): void
    {
        Gate::authorize('create', Warehouse::class);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function save(CreateWarehouse $action): void
    {
        $validated = $this->validate();

        $action->handle(auth()->user(), [
            'name' => $validated['name'],
            'address' => $validated['address'] !== '' ? $validated['address'] : null,
            'is_active' => $validated['is_active'],
        ]);

        $this->success('انبار جدید با موفقیت ثبت شد.', redirectTo: route('inventory.warehouses.index'));
    }

    public function render()
    {
        return view('livewire.inventory.warehouse-form');
    }
}
