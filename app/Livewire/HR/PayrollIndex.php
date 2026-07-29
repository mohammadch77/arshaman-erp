<?php

namespace App\Livewire\HR;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Actions\CalculatePayroll;
use App\Modules\HR\Actions\FinalizePayrollRun;
use App\Modules\HR\Models\PayrollRun;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;
use Morilog\Jalali\Jalalian;

class PayrollIndex extends Component
{
    use Toast;

    public ?int $year = null;

    public ?int $month = null;

    public function mount(): void
    {
        $this->authorize('viewAny', PayrollRun::class);

        $now = Jalalian::now();
        $this->year = $now->getYear();
        $this->month = $now->getMonth();
    }

    public function getPeriodMonthProperty(): string
    {
        return sprintf('%04d-%02d', (int) $this->year, (int) $this->month);
    }

    public function getRunProperty(): ?PayrollRun
    {
        // بدون withoutGlobalScopes — دوره حقوق باید همیشه از دید شرکت فعال دیده شود.
        return PayrollRun::query()->where('period_month', $this->periodMonth)->first();
    }

    public function getPayslipsProperty()
    {
        return $this->run?->payslips()->with('employee')->get()
            ->sortBy(fn ($payslip) => $payslip->employee?->full_name)
            ->values() ?? collect();
    }

    public function getTotalNetProperty(): string
    {
        return $this->payslips->reduce(
            fn (string $carry, $payslip) => Money::add($carry, (string) $payslip->net_amount),
            '0'
        );
    }

    public function calculate(CalculatePayroll $action): void
    {
        // لایه اول authorize؛ لایه دوم داخل خود Action است — CLAUDE.md بند ۹.
        $this->authorize('calculate', PayrollRun::class);

        $company = app(CompanyContext::class)->activeCompany();

        if (! $company) {
            $this->error('برای محاسبه حقوق، یک شرکت مشخص را از سوییچر بالا انتخاب کنید.');

            return;
        }

        try {
            $action->handle($company, $this->periodMonth, auth()->user());
        } catch (ValidationException $exception) {
            // پیام‌های این Action عمداً طولانی و راهنما هستند (مثلاً فهرست کارمندانی
            // که کارکرد ماهانه‌شان محاسبه نشده) — پس به‌جای error bag، toast می‌شوند.
            $this->error(collect($exception->errors())->flatten()->implode(' '), timeout: 12000);

            return;
        }

        $this->success('حقوق این دوره محاسبه شد.');
    }

    public function finalize(FinalizePayrollRun $action): void
    {
        $this->authorize('finalize', PayrollRun::class);

        $run = $this->run;

        if (! $run) {
            $this->error('برای این ماه هنوز دوره حقوقی محاسبه نشده است.');

            return;
        }

        try {
            $action->handle($run, auth()->user());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return;
        }

        $this->success('دوره حقوق نهایی شد و از این پس قابل تغییر نیست.');
    }

    public function render()
    {
        return view('livewire.hr.payroll-index', [
            'run' => $this->run,
            'payslips' => $this->payslips,
        ]);
    }
}
