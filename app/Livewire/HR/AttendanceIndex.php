<?php

namespace App\Livewire\HR;

use App\Modules\HR\Actions\RecordAttendance;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Support\Jalali;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class AttendanceIndex extends Component
{
    use Toast, WithPagination;

    public string $filterEmployeeId = '';

    public bool $showForm = false;

    public string $formEmployeeId = '';

    public string $attendance_date = '';

    public string $check_in_time = '';

    public string $check_out_time = '';

    /**
     * @var array<string, array{year: ?int, month: ?int, day: ?int}>
     */
    public array $jalaliParts = [
        'attendance_date' => ['year' => null, 'month' => null, 'day' => null],
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Attendance::class);
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
        $this->authorize('recordAny', Attendance::class);

        $this->formEmployeeId = '';
        $this->attendance_date = '';
        $this->check_in_time = '';
        $this->check_out_time = '';
        $this->jalaliParts['attendance_date'] = ['year' => null, 'month' => null, 'day' => null];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(RecordAttendance $action): void
    {
        $this->validate([
            'formEmployeeId' => ['required', 'uuid', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
        ]);

        $employee = Employee::findOrFail($this->formEmployeeId);

        $times = [];

        if ($this->check_in_time !== '') {
            $times['check_in_at'] = "{$this->attendance_date} {$this->check_in_time}:00";
        }

        if ($this->check_out_time !== '') {
            $times['check_out_at'] = "{$this->attendance_date} {$this->check_out_time}:00";
        }

        $action->handle($employee, $this->attendance_date, $times, auth()->user(), RecordedBy::Admin);

        $this->showForm = false;
        $this->success('حضور ثبت شد.');
    }

    public function updatedFilterEmployeeId(): void
    {
        $this->resetPage();
    }

    public function getEmployeeOptionsProperty()
    {
        return Employee::orderBy('full_name')->get(['id', 'full_name']);
    }

    public function getAttendancesProperty()
    {
        return Attendance::query()
            ->with('employee')
            ->when($this->filterEmployeeId, fn ($query) => $query->where('employee_id', $this->filterEmployeeId))
            ->orderByDesc('attendance_date')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.hr.attendance-index', [
            'attendances' => $this->attendances,
        ]);
    }
}
