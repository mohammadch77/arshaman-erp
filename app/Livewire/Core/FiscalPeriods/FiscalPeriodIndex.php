<?php

namespace App\Livewire\Core\FiscalPeriods;

use App\Modules\Core\Actions\CloseFiscalPeriod;
use App\Modules\Core\Models\FiscalPeriod;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

class FiscalPeriodIndex extends Component
{
    use Toast;

    public function mount(): void
    {
        $this->authorize('viewAny', FiscalPeriod::class);
    }

    public function getPeriodsProperty()
    {
        return FiscalPeriod::query()->orderByDesc('start_date')->get();
    }

    /**
     * بستن سال مالی غیرقابل بازگشت است — هیچ Action «بازگشایی» برای این جدول
     * وجود ندارد (برخلاف حقوق)، پس تأییدیه در لایه UI (wire:confirm) کافی است.
     */
    public function close(string $fiscalPeriodId, CloseFiscalPeriod $action): void
    {
        $fiscalPeriod = FiscalPeriod::findOrFail($fiscalPeriodId);

        $this->authorize('close', $fiscalPeriod);

        try {
            $action->handle($fiscalPeriod, auth()->user());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return;
        }

        $this->success('سال مالی بسته شد.');
    }

    public function render()
    {
        return view('livewire.core.fiscal-periods.fiscal-period-index', [
            'periods' => $this->periods,
        ]);
    }
}
