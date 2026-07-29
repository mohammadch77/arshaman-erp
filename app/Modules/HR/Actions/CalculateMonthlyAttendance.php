<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use App\Modules\HR\Services\WorkCalendar;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CalculateMonthlyAttendance
{
    /**
     * برای یک کارمند/ماه: از WorkCalendar روزهای کاری آن ماه گرفته می‌شود؛ برای هر روز
     * کاری بدون attendance ثبت‌شده → غیبت. مرخصی هنوز ساخته نشده (Session 5)، پس
     * total_leave_days همیشه صفر می‌ماند. Idempotent — رکورد قبلی همان ماه/کارمند جایگزین می‌شود.
     *
     * @param  string  $periodMonth  شمسی مثل '1405-04'
     */
    public function handle(Employee $employee, string $periodMonth, User $actor): MonthlyAttendanceSummary
    {
        Gate::forUser($actor)->authorize('calculate', MonthlyAttendanceSummary::class);

        Validator::make(['period_month' => $periodMonth], [
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ])->validate();

        [$year, $month] = array_map('intval', explode('-', $periodMonth));

        $startDate = Carbon::parse(Jalali::toGregorian($year, $month, 1));
        $endDate = Carbon::parse(Jalali::toGregorian($year, $month, Jalali::daysInMonth($year, $month)));

        $attendancesByDate = Attendance::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $attendance) => $attendance->attendance_date->toDateString());

        $workCalendar = app(WorkCalendar::class);
        $workedDays = 0;
        $absentDays = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (! $workCalendar->isWorkday($date, $employee->owner_company_id)) {
                continue;
            }

            if ($attendancesByDate->has($date->toDateString())) {
                $workedDays++;
            } else {
                $absentDays++;
            }
        }

        return DB::transaction(fn () => MonthlyAttendanceSummary::withoutGlobalScopes()->updateOrCreate(
            ['employee_id' => $employee->id, 'period_month' => $periodMonth],
            [
                'owner_company_id' => $employee->owner_company_id,
                'total_worked_days' => $workedDays,
                'total_absent_days' => $absentDays,
                'total_late_minutes' => (int) $attendancesByDate->sum('late_minutes'),
                'total_overtime_minutes' => (int) $attendancesByDate->sum('overtime_minutes'),
                'total_leave_days' => 0,
                'calculated_at' => now(),
            ]
        ));
    }
}
