<?php

namespace App\Livewire\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Inventory\Actions\IssueStock;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Mary\Traits\Toast;

class StockMovementForm extends Component
{
    use Toast;

    public string $movementType = 'in';

    public string $product_id = '';

    public string $warehouse_id = '';

    public string $quantity = '';

    public string $reason = '';

    public function mount(string $type = 'in'): void
    {
        $this->movementType = $type === 'out' ? 'out' : 'in';
        Gate::authorize('manage', Stock::class);
    }

    public function getProductOptionsProperty(): array
    {
        return Product::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Product $product) => ['id' => $product->id, 'name' => $product->name])
            ->all();
    }

    public function getWarehouseOptionsProperty(): array
    {
        return Warehouse::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'name' => $warehouse->name])
            ->all();
    }

    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function save(ReceiveStock $receiveAction, IssueStock $issueAction, CompanyContext $companyContext): void
    {
        $data = $this->validate();
        $data['owner_company_id'] = $companyContext->id();
        $data['reason'] = $data['reason'] ?: null;

        try {
            if ($this->movementType === 'out') {
                $issueAction->handle($data, auth()->user());
                $this->success('خروج کالا ثبت شد.', redirectTo: route('inventory.stock.index'));

                return;
            }

            $receiveAction->handle($data, auth()->user());
            $this->success('دریافت کالا ثبت شد.', redirectTo: route('inventory.stock.index'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventory.stock-movement-form');
    }
}
