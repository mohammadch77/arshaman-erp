<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\WorkCalendar;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CalculateMonthlyAttendance
{
    /**
     * برای یک کارمند/ماه: از WorkCalendar روزهای کاری آن ماه گرفته می‌شود؛ برای هر روز کاری:
     * اگر attendance ثبت شده → کارکرد. وگرنه اگر مرخصی approved آن روز را پوشش می‌دهد → مرخصی
     * (نه غیبت). وگرنه → غیبت. Idempotent — رکورد قبلی همان ماه/کارمند جایگزین می‌شود.
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

        // groupBy و نه keyBy: از وقتی هر ردیف یک تردد است، یک روز می‌تواند چند
        // ردیف داشته باشد. keyBy روی کلید تکراری فقط **آخرین** رکورد را نگه
        // می‌داشت و ترددهای قبلی همان روز بی‌سروصدا از محاسبه حذف می‌شدند.
        $punchesByDate = Attendance::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy(fn (Attendance $attendance) => $attendance->attendance_date->toDateString());

        // withoutGlobalScope('owner_company') و نه withoutGlobalScopes() — دومی
        // scope حذف نرم را هم برمی‌داشت و مرخصی حذف‌شده باز هم روز را از غیبت
        // خارج می‌کرد.
        //
        // مرخصی ساعتی عمداً کنار گذاشته می‌شود: کارمند آن روز سرِ کار بوده و فقط
        // چند ساعت مرخصی داشته. اگر اینجا می‌آمد، یک مرخصی یک‌ساعته کل روز را
        // «مرخصی» می‌کرد و هم از غیبت خارجش می‌کرد هم یک روز کامل به
        // total_leave_days اضافه می‌کرد.
        $approvedLeaves = Leave::withoutGlobalScope('owner_company')
            ->where('employee_id', $employee->id)
            ->where('leave_status', LeaveStatus::Approved)
            ->where('leave_type', '!=', LeaveType::Hourly)
            // whereDate چون ستون تاریخ به‌شکل کامل datetime ذخیره می‌شود؛ با
            // مقایسه رشته‌ای خام، مرخصی‌ای که دقیقاً روزِ آخر ماه شروع می‌شد از
            // قلم می‌افتاد و آن روز به‌اشتباه «غیبت» شمرده می‌شد.
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->get();

        $isOnApprovedLeave = fn (Carbon $date): bool => $approvedLeaves
            ->contains(fn (Leave $leave) => $date->between($leave->start_date, $leave->end_date));

        $workCalendar = app(WorkCalendar::class);
        $calculator = app(AttendanceCalculator::class);
        $workedDays = 0;
        $absentDays = 0;
        $leaveDays = 0;
        $lateMinutes = 0;
        $overtimeMinutes = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (! $workCalendar->isWorkday($date, $employee->owner_company_id)) {
                continue;
            }

            if ($punchesByDate->has($date->toDateString())) {
                $workedDays++;
            } elseif ($isOnApprovedLeave($date->copy())) {
                $leaveDays++;
            } else {
                $absentDays++;
            }
        }

        // کسری و اضافه‌کاری در سطح **روز** محاسبه می‌شوند (مجموع همه ترددهای آن
        // روز در برابر روز کاری استاندارد) و بعد جمع می‌شوند — نه جمع ستون‌های
        // ردیفی که دیگر وجود ندارند. روزهای غیرکاری هم شمرده می‌شوند چون کارکرد
        // در تعطیلی، اضافه‌کاری واقعی است.
        foreach ($punchesByDate as $punchesOfDay) {
            [$dayShortfall, $dayOvertime] = $calculator->minutesForDay($punchesOfDay);

            $lateMinutes += $dayShortfall;
            $overtimeMinutes += $dayOvertime;
        }

        return DB::transaction(fn () => MonthlyAttendanceSummary::withoutGlobalScopes()->updateOrCreate(
            ['employee_id' => $employee->id, 'period_month' => $periodMonth],
            [
                'owner_company_id' => $employee->owner_company_id,
                'total_worked_days' => $workedDays,
                'total_absent_days' => $absentDays,
                'total_late_minutes' => $lateMinutes,
                'total_overtime_minutes' => $overtimeMinutes,
                'total_leave_days' => $leaveDays,
                'calculated_at' => now(),
            ]
        ));
    }
}
