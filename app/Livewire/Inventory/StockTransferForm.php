<?php

namespace App\Livewire\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class StockTransferForm extends Component
{
    use Toast, WithPagination;

    public string $product_id = '';

    public string $from_warehouse_id = '';

    public string $to_warehouse_id = '';

    public string $quantity = '';

    public string $note = '';

    public function mount(): void
    {
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

    /**
     * موجودی فعلی محصول/انبار مبدأ انتخاب‌شده — فقط برای کمک بصری به کاربر
     * قبل از ثبت؛ منبع حقیقت واقعی همچنان چک سطح Action است.
     */
    public function getFromStockQuantityProperty(): ?string
    {
        if ($this->product_id === '' || $this->from_warehouse_id === '') {
            return null;
        }

        $stock = Stock::query()
            ->where('product_id', $this->product_id)
            ->where('warehouse_id', $this->from_warehouse_id)
            ->first();

        return $stock?->quantity_on_hand;
    }

    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'from_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'to_warehouse_id.different' => 'انبار مبدأ و مقصد نمی‌توانند یکسان باشند.',
        ];
    }

    public function save(TransferStock $action, CompanyContext $companyContext): void
    {
        $validated = $this->validate();

        try {
            $action->handle([
                'owner_company_id' => $companyContext->id(),
                'product_id' => $validated['product_id'],
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'quantity' => $validated['quantity'],
                'note' => $validated['note'] !== '' ? $validated['note'] : null,
            ], auth()->user());

            $this->reset(['product_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity', 'note']);
            $this->success('جابجایی موجودی با موفقیت ثبت شد.');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        }
    }

    public function getTransfersProperty()
    {
        return StockTransfer::query()
            ->with(['product', 'fromWarehouse', 'toWarehouse', 'createdBy'])
            ->latest('created_at')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.inventory.stock-transfer-form', [
            'transfers' => $this->transfers,
        ]);
    }
}
