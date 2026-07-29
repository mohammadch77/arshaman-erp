<?php

namespace App\Livewire\HR\SelfService;

use App\Modules\HR\Actions\RequestLeave;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Support\Jalali;
use Livewire\Component;
use Mary\Traits\Toast;

class MyLeaves extends Component
{
    use Toast;

    public ?string $employeeId = null;

    public bool $showForm = false;

    public string $leave_type = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $reason = '';

    /**
     * @var array<string, array{year: ?int, month: ?int, day: ?int}>
     */
    public array $jalaliParts = [
        'start_date' => ['year' => null, 'month' => null, 'day' => null],
        'end_date' => ['year' => null, 'month' => null, 'day' => null],
    ];

    public function mount(): void
    {
        // withoutGlobalScopes چون کارمند لاگین‌شده ممکن است متعلق به شرکتی
        // غیر از شرکت فعال session جاری باشد؛ اینجا فقط اتصال user_id ملاک است.
        $employee = Employee::withoutGlobalScopes()->where('user_id', auth()->id())->first();

        $this->employeeId = $employee?->id;
    }

    public function updatedJalaliParts($value, $key): void
    {
        [$field] = explode('.', $key);

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

    public function openForm(): void
    {
        $this->leave_type = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->reason = '';
        $this->jalaliParts = [
            'start_date' => ['year' => null, 'month' => null, 'day' => null],
            'end_date' => ['year' => null, 'month' => null, 'day' => null],
        ];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(RequestLeave $action): void
    {
        $this->validate([
            'leave_type' => ['required', 'in:'.implode(',', array_column(LeaveType::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        $employee = Employee::withoutGlobalScopes()->findOrFail($this->employeeId);

        $action->handle(
            $employee,
            [
                'leave_type' => $this->leave_type,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'reason' => $this->reason !== '' ? $this->reason : null,
            ],
            auth()->user(),
            RecordedBy::SelfService,
        );

        $this->showForm = false;
        $this->success('درخواست مرخصی ثبت شد.');
    }

    public function getLeaveTypeOptionsProperty(): array
    {
        return LeaveType::options();
    }

    public function getLeavesProperty()
    {
        if (! $this->employeeId) {
            return collect();
        }

        return Leave::withoutGlobalScopes()
            ->where('employee_id', $this->employeeId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.hr.self-service.my-leaves', [
            'leaves' => $this->leaves,
        ]);
    }
}
