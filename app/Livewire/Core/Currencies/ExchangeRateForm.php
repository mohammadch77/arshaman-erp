<?php

namespace App\Livewire\Core\Currencies;

use App\Modules\Core\Actions\RecordExchangeRate;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\ExchangeRate;
use App\Support\Jalali;
use Livewire\Component;
use Mary\Traits\Toast;

class ExchangeRateForm extends Component
{
    use Toast;

    public string $currency_id = '';

    public string $rate_to_toman = '';

    public string $effective_date = '';

    /**
     * @var array<string, array{year: ?int, month: ?int, day: ?int}>
     */
    public array $jalaliParts = [
        'effective_date' => ['year' => null, 'month' => null, 'day' => null],
    ];

    public function mount(): void
    {
        $this->authorize('create', ExchangeRate::class);

        $today = Jalali::today();
        $this->effective_date = $today->toDateString();
        $this->jalaliParts['effective_date'] = Jalali::toJalaliParts($this->effective_date);
    }

    public function updatedJalaliParts($value, $key): void
    {
        [$field] = explode('.', $key);

        if (! property_exists($this, $field)) {
            return;
        }

        $year = $this->jalaliParts[$field]['year'] ?? null;
        $month = $this->jalaliParts[$field]['month'] ?? null;
        $day = $this->jalaliParts[$field]['day'] ?? null;

        if ($day && $month) {
            $maxDay = Jalali::maxDayForMonth($year, $month);

            if ((int) $day > $maxDay) {
                $day = $maxDay;
                $this->jalaliParts[$field]['day'] = $maxDay;
            }
        }

        $this->{$field} = Jalali::toGregorian($year, $month, $day) ?? '';
    }

    public function getCurrencyOptionsProperty(): array
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency) => ['id' => $currency->id, 'name' => "{$currency->code} — {$currency->name}"])
            ->all();
    }

    protected function rules(): array
    {
        return [
            'currency_id' => ['required', 'uuid', 'exists:currencies,id'],
            'rate_to_toman' => ['required', 'numeric', 'min:0.01'],
            'effective_date' => ['required', 'date'],
        ];
    }

    public function save(RecordExchangeRate $action): void
    {
        $data = $this->validate();

        $action->handle($data, auth()->user());

        $this->success('نرخ ارز ثبت شد.', redirectTo: route('exchange-rates.index'));
    }

    public function render()
    {
        return view('livewire.core.currencies.exchange-rate-form');
    }
}
