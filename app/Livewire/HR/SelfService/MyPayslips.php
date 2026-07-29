<?php

namespace App\Livewire\HR\SelfService;

use App\Modules\HR\Enums\PayrollStatus;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Payslip;
use Livewire\Component;

class MyPayslips extends Component
{
    public ?string $employeeId = null;

    public ?string $selectedPayslipId = null;

    public function mount(): void
    {
        // withoutGlobalScopes چون کارمند لاگین‌شده ممکن است متعلق به شرکتی غیر از
        // شرکت فعال session باشد؛ اینجا فقط اتصال user_id ملاک است (همان الگوی MyLeaves).
        $employee = Employee::withoutGlobalScopes()->where('user_id', auth()->id())->first();

        $this->employeeId = $employee?->id;
    }

    /**
     * فقط فیش‌های دوره‌های نهایی‌شده. تا لحظه finalize مبالغ می‌توانند با بازمحاسبه
     * عوض شوند، پس کارمند نباید عددی ببیند که فردا فرق می‌کند.
     */
    public function getPayslipsProperty()
    {
        if (! $this->employeeId) {
            return collect();
        }

        // withoutGlobalScopes باید روی رابطه‌ها هم تکرار شود، نه فقط query پایه:
        // هم whereHas و هم eager load، Global Scope شرکتِ PayrollRun/Employee را
        // اعمال می‌کنند و کاربر self-service معمولاً شرکت فعالی ندارد — نتیجه‌اش
        // فهرست خالی و رابطه null می‌شود. ملاک دسترسی اینجا فقط user_id است.
        $withoutScopes = fn ($query) => $query->withoutGlobalScopes();

        return Payslip::withoutGlobalScopes()
            ->with(['payrollRun' => $withoutScopes, 'employee' => $withoutScopes])
            ->where('employee_id', $this->employeeId)
            ->whereHas('payrollRun', fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('payroll_status', PayrollStatus::Finalized)
            )
            ->get()
            ->sortByDesc(fn (Payslip $payslip) => $payslip->payrollRun->period_month)
            ->values();
    }

    public function getSelectedPayslipProperty(): ?Payslip
    {
        if (! $this->selectedPayslipId) {
            return null;
        }

        $payslip = $this->payslips->firstWhere('id', $this->selectedPayslipId);

        // نگهبان نهایی: حتی اگر شناسه دستی در URL/DOM دستکاری شود، Policy تصمیم
        // می‌گیرد — نه فهرست بالا. CLAUDE.md بند ۹ و «نکات حیاتی» بند ۳ سند HR.
        if (! $payslip || ! auth()->user()->can('viewOwn', $payslip)) {
            return null;
        }

        return $payslip;
    }

    public function select(string $payslipId): void
    {
        $this->selectedPayslipId = $payslipId;
    }

    public function render()
    {
        return view('livewire.hr.self-service.my-payslips', [
            'payslips' => $this->payslips,
            'selectedPayslip' => $this->selectedPayslip,
        ]);
    }
}
