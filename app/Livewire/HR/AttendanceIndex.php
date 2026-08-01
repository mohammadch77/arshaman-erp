<?php

namespace App\Livewire\HR;

use App\Modules\HR\Actions\RecordAttendance;
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

    public ?string $editingAttendanceId = null;

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

        $this->editingAttendanceId = null;
        $this->formEmployeeId = '';
        $this->attendance_date = '';
        $this->check_in_time = '';
        $this->check_out_time = '';
        $this->jalaliParts['attendance_date'] = ['year' => null, 'month' => null, 'day' => null];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    /**
     * ویرایش یک رکورد موجود — از جمله رکوردهایی که خودِ کارمند ثبت کرده
     * (recorded_by = self). Action از قبل update-or-create بود، پس ویرایش
     * فقط یعنی فرم را با مقادیر موجود پر کنیم و همان save را صدا بزنیم.
     *
     * ادمین برخلاف کارمند هیچ محدودیت پنجره زمانی ندارد — گارد پنجره فقط روی
     * RecordedBy::SelfService اعمال می‌شود.
     */
    public function edit(string $attendanceId): void
    {
        $this->authorize('recordAny', Attendance::class);

        $attendance = Attendance::findOrFail($attendanceId);

        $this->editingAttendanceId = $attendance->id;
        $this->formEmployeeId = $attendance->employee_id;
        $this->attendance_date = $attendance->attendance_date->toDateString();
        $this->jalaliParts['attendance_date'] = Jalali::toJalaliParts($attendance->attendance_date);
        // ساعت محلی، نه UTC: ادمین باید همان عددی را ببیند که کارمند واقعاً ثبت
        // کرده. اگر UTC نشان داده می‌شد، هر «اصلاحی» روی آن، داده درست را خراب
        // می‌کرد.
        $this->check_in_time = Jalali::local($attendance->check_in_at)?->format('H:i') ?? '';
        $this->check_out_time = Jalali::local($attendance->check_out_at)?->format('H:i') ?? '';
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

        // کلید خالی به‌معنای «پاک‌کردن آن سرِ تردد» است، نه «دست‌نزدن» — تا ادمین
        // بتواند یک خروج اشتباه را بردارد و تردد را دوباره باز کند.
        // ساعتی که ادمین وارد می‌کند وقت محلی است؛ ذخیره همیشه UTC می‌ماند
        // (بند ۳ CLAUDE.md).
        $times = [
            'check_in_at' => $this->check_in_time !== ''
                ? Jalali::fromLocal("{$this->attendance_date} {$this->check_in_time}:00")
                : null,
            'check_out_at' => $this->check_out_time !== ''
                ? Jalali::fromLocal("{$this->attendance_date} {$this->check_out_time}:00")
                : null,
        ];

        // هدف صریح است: از وقتی هر روز می‌تواند چند تردد داشته باشد، «رکورد آن
        // روز» یکتا نیست و استنتاج آن از تاریخ، تردد اشتباهی را بازنویسی می‌کرد.
        $target = $this->editingAttendanceId
            ? Attendance::findOrFail($this->editingAttendanceId)
            : null;

        $action->handle($employee, $this->attendance_date, $times, auth()->user(), $target);

        $wasEditing = $this->editingAttendanceId !== null;

        $this->showForm = false;
        $this->editingAttendanceId = null;
        $this->success($wasEditing ? 'حضور ویرایش شد.' : 'حضور ثبت شد.');
    }

    public function updatedFilterEmployeeId(): void
    {
        $this->resetPage();
    }

    public function getEmployeeOptionsProperty()
    {
        return Employee::orderBy('full_name')->get(['id', 'full_name']);
    }

    /**
     * فهرست مسطح: یک ردیف به ازای هر تردد. یک کارمند/روز می‌تواند چند ردیف
     * داشته باشد، پس ترتیب ثانویه روی ساعت ورود است تا ترددهای یک روز پشت سر
     * هم و به ترتیب زمانی دیده شوند.
     */
    public function getAttendancesProperty()
    {
        return Attendance::query()
            ->with('employee')
            ->when($this->filterEmployeeId, fn ($query) => $query->where('employee_id', $this->filterEmployeeId))
            ->orderByDesc('attendance_date')
            ->orderBy('check_in_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.hr.attendance-index', [
            'attendances' => $this->attendances,
        ]);
    }
}
