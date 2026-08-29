<?php

namespace App\Livewire\Inventory;

use App\Modules\Inventory\Actions\CreateWarehouse;
use App\Modules\Inventory\Actions\UpdateWarehouse;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Mary\Traits\Toast;

class WarehouseForm extends Component
{
    use Toast;

    public ?Warehouse $record = null;

    public string $name = '';

    public string $address = '';

    public bool $is_active = true;

    public function mount(?string $warehouse = null): void
    {
        if ($warehouse) {
            $this->record = Warehouse::findOrFail($warehouse);
            Gate::authorize('update', Warehouse::class);

            $this->name = $this->record->name;
            $this->address = (string) ($this->record->address ?? '');
            $this->is_active = $this->record->is_active;

            return;
        }

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

    public function save(CreateWarehouse $createAction, UpdateWarehouse $updateAction): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'address' => $validated['address'] !== '' ? $validated['address'] : null,
            'is_active' => $validated['is_active'],
        ];

        if ($this->record) {
            $updateAction->handle($this->record, auth()->user(), $data);
            $this->success('انبار به‌روزرسانی شد.', redirectTo: route('inventory.warehouses.index'));

            return;
        }

        $createAction->handle(auth()->user(), $data);

        $this->success('انبار جدید با موفقیت ثبت شد.', redirectTo: route('inventory.warehouses.index'));
    }

    public function render()
    {
        return view('livewire.inventory.warehouse-form');
    }
}
