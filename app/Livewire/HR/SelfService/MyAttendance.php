<?php

namespace App\Livewire\HR\SelfService;

use App\Modules\HR\Actions\RecordAttendance;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Mary\Traits\Toast;

class MyAttendance extends Component
{
    use Toast;

    public ?string $employeeId = null;

    public ?string $employeeFullName = null;

    public ?string $checkInAt = null;

    public ?string $checkOutAt = null;

    public function mount(): void
    {
        // withoutGlobalScopes چون کارمند لاگین‌شده ممکن است متعلق به شرکتی
        // غیر از شرکت فعال session جاری باشد؛ اینجا فقط اتصال user_id ملاک است.
        $employee = Employee::withoutGlobalScopes()->where('user_id', auth()->id())->first();

        if (! $employee) {
            return;
        }

        $this->employeeId = $employee->id;
        $this->employeeFullName = $employee->full_name;

        $this->loadToday();
    }

    protected function loadToday(): void
    {
        $today = Attendance::withoutGlobalScopes()
            ->where('employee_id', $this->employeeId)
            ->whereDate('attendance_date', Carbon::today())
            ->first();

        $this->checkInAt = $today?->check_in_at?->format('H:i');
        $this->checkOutAt = $today?->check_out_at?->format('H:i');
    }

    public function checkIn(RecordAttendance $action): void
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($this->employeeId);

        $action->handle(
            $employee,
            Carbon::today()->toDateString(),
            ['check_in_at' => now()],
            auth()->user(),
            RecordedBy::SelfService,
        );

        $this->loadToday();
        $this->success('ورود ثبت شد.');
    }

    public function checkOut(RecordAttendance $action): void
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($this->employeeId);

        $action->handle(
            $employee,
            Carbon::today()->toDateString(),
            ['check_out_at' => now()],
            auth()->user(),
            RecordedBy::SelfService,
        );

        $this->loadToday();
        $this->success('خروج ثبت شد.');
    }

    public function render()
    {
        return view('livewire.hr.self-service.my-attendance');
    }
}
