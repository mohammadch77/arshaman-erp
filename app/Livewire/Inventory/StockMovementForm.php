<?php

namespace App\Livewire\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Actions\IssueStock;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Mary\Traits\Toast;

class StockMovementForm extends Component
{
    use Toast;

    public string $movementType = MovementType::PurchaseIn->value;

    public string $product_id = '';

    public string $warehouse_id = '';

    public string $quantity = '';

    public string $unit_cost = '';

    public string $reference_note = '';

    /**
     * @param  'in'|'out'|'adjust'  $type  دسته کلی، برای پیش‌مقداردهی اولین گزینه‌ی همان دسته در URL/منو.
     */
    public function mount(string $type = 'in'): void
    {
        $this->movementType = match ($type) {
            'out' => MovementType::SaleOut->value,
            'adjust' => MovementType::AdjustmentIn->value,
            default => MovementType::PurchaseIn->value,
        };

        Gate::authorize('manage', Stock::class);
    }

    public function getMovementTypeOptionsProperty(): array
    {
        return collect(MovementType::cases())
            ->map(fn (MovementType $case) => ['id' => $case->value, 'name' => $case->label()])
            ->all();
    }

    public function getIsInboundProperty(): bool
    {
        return MovementType::from($this->movementType)->direction() === 'in';
    }

    public function getIsPurchaseProperty(): bool
    {
        return $this->movementType === MovementType::PurchaseIn->value;
    }

    public function getIsAdjustmentProperty(): bool
    {
        return in_array($this->movementType, [MovementType::AdjustmentIn->value, MovementType::AdjustmentOut->value], true);
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
            'movementType' => ['required', 'in:'.implode(',', array_column(MovementType::cases(), 'value'))],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'reference_note' => [$this->isAdjustment ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    public function save(ReceiveStock $receiveAction, IssueStock $issueAction, AdjustStock $adjustAction, CompanyContext $companyContext): void
    {
        $validated = $this->validate();

        $data = [
            'owner_company_id' => $companyContext->id(),
            'product_id' => $validated['product_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'quantity' => $validated['quantity'],
            'movement_type' => $validated['movementType'],
            'reference_note' => $validated['reference_note'] !== '' ? $validated['reference_note'] : null,
        ];

        try {
            $movementType = MovementType::from($validated['movementType']);

            match ($movementType) {
                MovementType::PurchaseIn, MovementType::ReturnIn => $receiveAction->handle(
                    [...$data, 'unit_cost' => $validated['unit_cost'] !== '' ? $validated['unit_cost'] : null],
                    auth()->user()
                ),
                MovementType::SaleOut, MovementType::WasteOut => $issueAction->handle($data, auth()->user()),
                MovementType::AdjustmentIn, MovementType::AdjustmentOut => $adjustAction->handle($data, auth()->user()),
            };

            $this->success('حرکت موجودی با موفقیت ثبت شد.', redirectTo: route('inventory.stock.index'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventory.stock-movement-form');
    }
}
