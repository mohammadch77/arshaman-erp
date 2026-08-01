<?php

namespace App\Livewire\HR\SelfService;

use App\Modules\HR\Actions\PunchAttendance;
use App\Modules\HR\Enums\PunchDirection;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Support\Jalali;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;
use Morilog\Jalali\Jalalian;

/**
 * پنل تردد خودِ کارمند.
 *
 * این کامپوننت عمداً **هیچ خصوصیت public قابل‌ویرایشی برای تاریخ یا ساعت ندارد**.
 * خصوصیات public در Livewire از سمت مرورگر قابل دستکاری‌اند؛ تا وقتی چنین
 * خصوصیتی وجود داشته باشد، «فقط دکمه گذاشتن در UI» تضمینی نمی‌سازد. زمان کاملاً
 * داخل PunchAttendance از ساعت سرور خوانده می‌شود.
 */
class MyAttendance extends Component
{
    use Toast;

    public ?string $employeeId = null;

    public ?string $employeeFullName = null;

    public function mount(): void
    {
        // withoutGlobalScopes چون کارمند لاگین‌شده ممکن است متعلق به شرکتی
        // غیر از شرکت فعال session جاری باشد؛ اینجا فقط اتصال user_id ملاک است.
        $employee = Employee::withoutGlobalScopes()->where('user_id', auth()->id())->first();

        $this->employeeId = $employee?->id;
        $this->employeeFullName = $employee?->full_name;
    }

    public function checkIn(PunchAttendance $action): void
    {
        $this->punch($action, PunchDirection::In, 'ورود شما ثبت شد.');
    }

    public function checkOut(PunchAttendance $action): void
    {
        $this->punch($action, PunchDirection::Out, 'خروج شما ثبت شد.');
    }

    protected function punch(PunchAttendance $action, PunchDirection $direction, string $message): void
    {
        if (! $this->employeeId) {
            return;
        }

        $employee = Employee::withoutGlobalScopes()->findOrFail($this->employeeId);

        try {
            $action->handle($employee, $direction, auth()->user());
        } catch (ValidationException $exception) {
            // پیام‌های این Action برای کاربر معنادارند (مثلاً تردد باز از چه
            // تاریخی مانده)، پس به‌جای error bag خام به‌صورت toast می‌آیند.
            $this->error(collect($exception->errors())->flatten()->implode(' '), timeout: 10000);

            return;
        }

        $this->success($message);
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function getTodayPunchesProperty(): Collection
    {
        if (! $this->employeeId) {
            return collect();
        }

        // «امروز» به وقت محلی، نه به وقت UTC سرور — وگرنه بین نیمه‌شب تهران و
        // نیمه‌شب UTC، لاگ امروزِ کارمند خالی به‌نظر می‌رسید.
        return Attendance::withoutGlobalScopes()
            ->where('employee_id', $this->employeeId)
            ->whereDate('attendance_date', Jalali::today()->toDateString())
            ->orderBy('check_in_at')
            ->get();
    }

    public function getHasOpenPunchProperty(): bool
    {
        if (! $this->employeeId) {
            return false;
        }

        // بدون قید تاریخ: تردد بازِ دیشب (شیفت شبانه) هم باید دکمه خروج را فعال
        // نگه دارد، وگرنه کارمند شیفت شب هرگز نمی‌تواند خروج بزند.
        return Attendance::withoutGlobalScopes()
            ->where('employee_id', $this->employeeId)
            ->whereNull('check_out_at')
            ->exists();
    }

    public function getTodayWorkedMinutesProperty(): int
    {
        return app(AttendanceCalculator::class)->workedMinutes($this->todayPunches);
    }

    public function getTodayBalanceProperty(): int
    {
        return app(AttendanceCalculator::class)->balanceForDay($this->todayPunches);
    }

    public function getTodayIsClosedProperty(): bool
    {
        return $this->todayPunches->isNotEmpty()
            && ! app(AttendanceCalculator::class)->hasOpenPunch($this->todayPunches);
    }

    /**
     * ترددهای ماه شمسی جاری، گروه‌بندی‌شده بر اساس روز — چون کسری/اضافه‌کاری
     * روزانه است و یک روز می‌تواند چند تردد داشته باشد.
     *
     * @return Collection<string, Collection<int, Attendance>>
     */
    public function getPunchesByDayProperty(): Collection
    {
        if (! $this->employeeId) {
            return collect();
        }

        $now = Jalalian::fromCarbon(Jalali::today());
        $monthStart = Carbon::parse(Jalali::toGregorian($now->getYear(), $now->getMonth(), 1));

        return Attendance::withoutGlobalScopes()
            ->where('employee_id', $this->employeeId)
            ->whereDate('attendance_date', '>=', $monthStart->toDateString())
            ->orderByDesc('attendance_date')
            ->orderBy('check_in_at')
            ->get()
            ->groupBy(fn (Attendance $punch) => $punch->attendance_date->toDateString());
    }

    public function render()
    {
        return view('livewire.hr.self-service.my-attendance', [
            'todayPunches' => $this->todayPunches,
            'punchesByDay' => $this->punchesByDay,
        ]);
    }
}
