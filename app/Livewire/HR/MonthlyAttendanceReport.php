<?php

namespace App\Livewire\HR;

use App\Modules\HR\Actions\CalculateMonthlyAttendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use Livewire\Component;
use Mary\Traits\Toast;
use Morilog\Jalali\Jalalian;

class MonthlyAttendanceReport extends Component
{
    use Toast;

    public string $filterEmployeeId = '';

    public ?int $year = null;

    public ?int $month = null;

    public function mount(): void
    {
        $this->authorize('viewSummary', MonthlyAttendanceSummary::class);

        $now = Jalalian::now();
        $this->year = $now->getYear();
        $this->month = $now->getMonth();
    }

    public function getPeriodMonthProperty(): string
    {
        return sprintf('%04d-%02d', (int) $this->year, (int) $this->month);
    }

    public function calculate(CalculateMonthlyAttendance $action): void
    {
        $this->authorize('calculate', MonthlyAttendanceSummary::class);

        $employees = $this->filterEmployeeId !== ''
            ? Employee::where('id', $this->filterEmployeeId)->get()
            : Employee::all();

        foreach ($employees as $employee) {
            $action->handle($employee, $this->periodMonth, auth()->user());
        }

        $this->success('کارکرد ماهانه محاسبه شد.');
    }

    public function getEmployeeOptionsProperty()
    {
        return Employee::orderBy('full_name')->get(['id', 'full_name']);
    }

    public function getSummariesProperty()
    {
        return MonthlyAttendanceSummary::query()
            ->with('employee')
            ->where('period_month', $this->periodMonth)
            ->when($this->filterEmployeeId, fn ($query) => $query->where('employee_id', $this->filterEmployeeId))
            ->get()
            ->sortBy(fn (MonthlyAttendanceSummary $summary) => $summary->employee->full_name);
    }

    public function render()
    {
        return view('livewire.hr.monthly-attendance-report', [
            'summaries' => $this->summaries,
        ]);
    }
}
