<?php

namespace App\Livewire\Core\Currencies;

use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\ExchangeRate;
use Livewire\Component;
use Livewire\WithPagination;

class ExchangeRateIndex extends Component
{
    use WithPagination;

    public string $currencyFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ExchangeRate::class);
    }

    public function updatedCurrencyFilter(): void
    {
        $this->resetPage();
    }

    public function getCurrencyFilterOptionsProperty(): array
    {
        return Currency::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency) => ['id' => $currency->id, 'name' => "{$currency->code} — {$currency->name}"])
            ->all();
    }

    public function getRatesProperty()
    {
        return ExchangeRate::query()
            ->with('currency', 'createdBy')
            ->when($this->currencyFilter, fn ($query) => $query->where('currency_id', $this->currencyFilter))
            ->orderByDesc('effective_date')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.core.currencies.exchange-rate-index', [
            'rates' => $this->rates,
        ]);
    }
}
