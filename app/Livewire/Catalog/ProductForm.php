<?php

namespace App\Livewire\Catalog;

use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Enums\FulfillmentType;
use App\Modules\Catalog\Enums\UnitOfMeasure;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Services\CompanyContext;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

class ProductForm extends Component
{
    use Toast;

    public ?Product $record = null;

    public string $name = '';

    public string $sku = '';

    public string $sale_price = '';

    public string $cost_price = '';

    public string $currency_id = '';

    public string $fulfillment_type = 'physical';

    public string $unit_of_measure = 'piece';

    public string $woocommerce_product_id = '';

    public bool $is_active = true;

    public string $reorder_point = '';

    public function mount(?string $product = null): void
    {
        if ($product) {
            $this->record = Product::findOrFail($product);
            $this->authorize('update', $this->record);

            $this->name = $this->record->name;
            $this->sku = (string) ($this->record->sku ?? '');
            $this->sale_price = (string) $this->record->sale_price;
            $this->cost_price = (string) ($this->record->cost_price ?? '');
            $this->currency_id = (string) $this->record->currency_id;
            $this->fulfillment_type = $this->record->fulfillment_type->value;
            $this->unit_of_measure = $this->record->unit_of_measure->value;
            $this->woocommerce_product_id = (string) $this->record->woocommerce_product_id;
            $this->is_active = $this->record->is_active;
            $this->reorder_point = (string) ($this->record->reorder_point ?? '');

            return;
        }

        $this->authorize('create', Product::class);
    }

    public function getFulfillmentTypeOptionsProperty(): array
    {
        return array_map(fn (FulfillmentType $case) => ['id' => $case->value, 'name' => $case->label()], FulfillmentType::cases());
    }

    public function getUnitOfMeasureOptionsProperty(): array
    {
        return array_map(fn (UnitOfMeasure $case) => ['id' => $case->value, 'name' => $case->label()], UnitOfMeasure::cases());
    }

    public function getCurrencyOptionsProperty(): array
    {
        return Currency::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Currency $currency) => ['id' => $currency->id, 'name' => "{$currency->name} ({$currency->code})"])
            ->all();
    }

    public function getShowsCostWarningProperty(): bool
    {
        return $this->cost_price === '';
    }

    protected function rules(): array
    {
        $companyId = $this->record?->owner_company_id ?? app(CompanyContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')
                    ->where('owner_company_id', $companyId)
                    ->ignore($this->record?->id),
            ],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'uuid', 'exists:currencies,id'],
            'fulfillment_type' => ['required', 'string'],
            'unit_of_measure' => ['required', 'string'],
            'woocommerce_product_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function save(CreateProduct $createAction, UpdateProduct $updateAction, CompanyContext $companyContext): void
    {
        $data = $this->validate();

        $data['sku'] = $data['sku'] !== null && $data['sku'] !== '' ? $data['sku'] : null;
        $data['cost_price'] = $data['cost_price'] !== null && $data['cost_price'] !== '' ? $data['cost_price'] : null;
        $data['currency_id'] = $data['currency_id'] ?: null;
        $data['woocommerce_product_id'] = $data['woocommerce_product_id'] ?: null;
        $data['reorder_point'] = $data['reorder_point'] !== null && $data['reorder_point'] !== '' ? $data['reorder_point'] : null;
        $data['category_id'] = null;

        if ($this->record) {
            $updateAction->handle($this->record, $data, auth()->user());
            $this->success('اطلاعات محصول به‌روزرسانی شد.', redirectTo: route('products.index'));

            return;
        }

        $data['owner_company_id'] = $companyContext->id();

        $createAction->handle($data, auth()->user());
        $this->success('محصول جدید ساخته شد.', redirectTo: route('products.index'));
    }

    public function render()
    {
        return view('livewire.catalog.product-form');
    }
}
