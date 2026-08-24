<?php

namespace App\Livewire\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Sales\Actions\CreateManualOrder;
use App\Modules\Sales\Enums\OrderSource;
use App\Modules\Sales\Models\Order;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Mary\Traits\Toast;

class OrderForm extends Component
{
    use Toast;

    public string $party_id = '';

    public string $source = '';

    public string $external_order_id = '';

    public string $shipping_amount = '0';

    /** @var array<int, array{product_id: string, quantity: string}> */
    public array $lines = [
        ['product_id' => '', 'quantity' => '1'],
    ];

    public function mount(): void
    {
        $this->authorize('create', [Order::class, app(CompanyContext::class)->id()]);
        $this->source = OrderSource::ManualInstagram->value;
    }

    public function getPartyOptionsProperty(): array
    {
        return Party::query()->where('is_customer', true)->orderBy('name')->get()
            ->map(fn (Party $party) => ['id' => $party->id, 'name' => $party->name])
            ->all();
    }

    public function getSourceOptionsProperty(): array
    {
        return collect(OrderSource::cases())
            ->filter(fn (OrderSource $case) => $case->isManual())
            ->map(fn (OrderSource $case) => ['id' => $case->value, 'name' => $case->label()])
            ->values()
            ->all();
    }

    public function getProductOptionsProperty(): array
    {
        return Product::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Product $product) => ['id' => $product->id, 'name' => $product->name])
            ->all();
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity' => '1'];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    protected function rules(): array
    {
        return [
            'party_id' => ['required', 'uuid', 'exists:parties,id'],
            'source' => ['required', 'in:'.implode(',', array_column(OrderSource::cases(), 'value'))],
            'external_order_id' => ['nullable', 'string', 'max:100'],
            'shipping_amount' => ['required', 'numeric', 'gte:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function save(CreateManualOrder $action, CompanyContext $companyContext): void
    {
        $validated = $this->validate();

        try {
            $action->handle([
                'owner_company_id' => $companyContext->id(),
                'party_id' => $validated['party_id'],
                'source' => $validated['source'],
                'external_order_id' => $validated['external_order_id'] !== '' ? $validated['external_order_id'] : null,
                'shipping_amount' => $validated['shipping_amount'],
                'lines' => $validated['lines'],
            ], auth()->user());

            $this->success('سفارش با موفقیت ثبت شد.', redirectTo: route('sales.orders.index'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());
        }
    }

    public function render()
    {
        return view('livewire.sales.order-form');
    }
}
