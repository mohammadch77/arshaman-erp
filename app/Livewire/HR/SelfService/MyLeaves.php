<?php

namespace App\Livewire\HR\SelfService;

use App\Modules\HR\Actions\DeleteLeaveRequest;
use App\Modules\HR\Actions\RequestLeave;
use App\Modules\HR\Actions\UpdateLeaveRequest;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Support\Jalali;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

class MyLeaves extends Component
{
    use Toast;

    public ?string $employeeId = null;

    public bool $showForm = false;

    public ?string $editingLeaveId = null;

    public string $leave_type = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $start_time = '';

    public string $end_time = '';

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
        $this->editingLeaveId = null;
        $this->leave_type = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->start_time = '';
        $this->end_time = '';
        $this->reason = '';
        $this->jalaliParts = [
            'start_date' => ['year' => null, 'month' => null, 'day' => null],
            'end_date' => ['year' => null, 'month' => null, 'day' => null],
        ];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    /**
     * ویرایش یک درخواست — فقط تا قبل از تصمیم مدیر. تصمیم نهایی با Policy داخل
     * خود Action است؛ این‌جا فقط فرم باز می‌شود.
     */
    public function edit(string $leaveId): void
    {
        $leave = Leave::withoutGlobalScope('owner_company')
            ->where('employee_id', $this->employeeId)
            ->findOrFail($leaveId);

        if (! auth()->user()->can('updateSelf', $leave)) {
            $this->error('این درخواست دیگر قابل ویرایش نیست.');

            return;
        }

        $this->editingLeaveId = $leave->id;
        $this->leave_type = $leave->leave_type->value;
        $this->start_date = $leave->start_date->toDateString();
        $this->end_date = $leave->end_date->toDateString();
        $this->start_time = (string) $leave->start_time;
        $this->end_time = (string) $leave->end_time;
        $this->reason = (string) $leave->reason;
        $this->jalaliParts = [
            'start_date' => Jalali::toJalaliParts($leave->start_date),
            'end_date' => Jalali::toJalaliParts($leave->end_date),
        ];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function delete(string $leaveId, DeleteLeaveRequest $action): void
    {
        $leave = Leave::withoutGlobalScope('owner_company')
            ->where('employee_id', $this->employeeId)
            ->findOrFail($leaveId);

        try {
            $action->handle($leave, auth()->user());
        } catch (AuthorizationException) {
            $this->error('این درخواست دیگر قابل حذف نیست.');

            return;
        }

        $this->success('درخواست مرخصی حذف شد.');
    }

    public function getIsHourlyProperty(): bool
    {
        return $this->leave_type === LeaveType::Hourly->value;
    }

    public function save(RequestLeave $requestAction, UpdateLeaveRequest $updateAction): void
    {
        // مرخصی ساعتی ذاتاً یک‌روزه است و فرم اصلاً فیلد «تا تاریخ» نشان نمی‌دهد؛
        // پس همین‌جا برابر تاریخ شروع می‌شود — هم برای اعتبارسنجی و هم برای اینکه
        // اگر کاربر نوع را از روزانه به ساعتی عوض کرد، تاریخ پایانِ قبلی جا نماند.
        if ($this->isHourly) {
            $this->end_date = $this->start_date;
        }

        $this->validate([
            'leave_type' => ['required', 'in:'.implode(',', array_column(LeaveType::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => [$this->isHourly ? 'required' : 'nullable', 'date_format:H:i'],
            'end_time' => [$this->isHourly ? 'required' : 'nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string'],
        ]);

        $data = [
            'leave_type' => $this->leave_type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->isHourly && $this->start_time !== '' ? $this->start_time : null,
            'end_time' => $this->isHourly && $this->end_time !== '' ? $this->end_time : null,
            'reason' => $this->reason !== '' ? $this->reason : null,
        ];

        $employee = Employee::withoutGlobalScopes()->findOrFail($this->employeeId);

        try {
            if ($this->editingLeaveId) {
                $leave = Leave::withoutGlobalScope('owner_company')
                    ->where('employee_id', $this->employeeId)
                    ->findOrFail($this->editingLeaveId);

                $updateAction->handle($leave, $data, auth()->user());
            } else {
                $requestAction->handle($employee, $data, auth()->user(), RecordedBy::SelfService);
            }
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return;
        } catch (AuthorizationException) {
            $this->error('این درخواست دیگر قابل ویرایش نیست.');

            return;
        }

        $wasEditing = $this->editingLeaveId !== null;

        $this->showForm = false;
        $this->editingLeaveId = null;
        $this->success($wasEditing ? 'درخواست مرخصی ویرایش شد.' : 'درخواست مرخصی ثبت شد.');
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

        // withoutGlobalScope('owner_company') و نه withoutGlobalScopes() — دومی
        // scope حذف نرم را هم برمی‌داشت و درخواست‌هایی که خودِ کارمند حذف کرده
        // باز هم در فهرستش دیده می‌شدند.
        return Leave::withoutGlobalScope('owner_company')
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
