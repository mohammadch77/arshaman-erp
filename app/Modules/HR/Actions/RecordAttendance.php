<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordAttendance
{
    // ساعت کاری استاندارد فعلاً ثابت — طبق Session 3 بعداً قابل‌تنظیم می‌شود.
    protected const WORKDAY_START_HOUR = 8;

    protected const WORKDAY_END_HOUR = 16;

    /**
     * @param  array{check_in_at?: string, check_out_at?: string}  $times  فقط کلیدهای واقعاً ارسالی روی رکورد اعمال می‌شوند
     */
    public function handle(Employee $employee, string $attendanceDate, array $times, User $actor, RecordedBy $recordedBy): Attendance
    {
        if ($recordedBy === RecordedBy::SelfService) {
            Gate::forUser($actor)->authorize('recordSelf', [Attendance::class, $employee]);

            if ($attendanceDate !== Carbon::today()->toDateString()) {
                throw ValidationException::withMessages([
                    'attendance_date' => 'کارمند فقط می‌تواند برای امروز حضور ثبت کند.',
                ]);
            }
        } else {
            Gate::forUser($actor)->authorize('recordAny', Attendance::class);
        }

        Validator::make(['attendance_date' => $attendanceDate], [
            'attendance_date' => ['required', 'date'],
        ])->validate();

        return DB::transaction(function () use ($employee, $attendanceDate, $times, $actor, $recordedBy) {
            // بدون scope شرکت فعال session جست‌وجو می‌شود چون owner_company_id همیشه
            // صریح از خودِ $employee گرفته می‌شود، نه از CompanyContext — تا در پنل
            // خودِ کارمند، ناهم‌خوانی شرکت فعال session با شرکت واقعی کارمند باعث
            // رکورد تکراری/خطای unique constraint نشود.
            // whereDate (نه یک شرط تساوی خام روی ستون) چون attendance_date در دیتابیس
            // با فرمت کامل datetime ذخیره می‌شود (رفتار پیش‌فرض cast تاریخ Eloquent)،
            // پس مقایسه مستقیم رشته با تاریخ خام ورودی هرگز match نمی‌شد.
            $attendance = Attendance::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $attendanceDate)
                ->first() ?? new Attendance([
                    'employee_id' => $employee->id,
                    'attendance_date' => $attendanceDate,
                ]);

            if (array_key_exists('check_in_at', $times)) {
                $attendance->check_in_at = $times['check_in_at'];
            }

            if (array_key_exists('check_out_at', $times)) {
                $attendance->check_out_at = $times['check_out_at'];
            }

            $attendance->owner_company_id = $employee->owner_company_id;
            $attendance->recorded_by = $recordedBy;
            $attendance->created_by_user_id ??= $actor->id;

            [$attendance->late_minutes, $attendance->overtime_minutes] = $this->calculateMinutes($attendance);

            $attendance->save();

            return $attendance;
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function calculateMinutes(Attendance $attendance): array
    {
        $day = Carbon::parse($attendance->attendance_date);
        $standardStart = $day->copy()->setTime(self::WORKDAY_START_HOUR, 0);
        $standardEnd = $day->copy()->setTime(self::WORKDAY_END_HOUR, 0);

        $late = $attendance->check_in_at && $attendance->check_in_at->gt($standardStart)
            ? (int) $standardStart->diffInMinutes($attendance->check_in_at)
            : 0;

        $overtime = $attendance->check_out_at && $attendance->check_out_at->gt($standardEnd)
            ? (int) $standardEnd->diffInMinutes($attendance->check_out_at)
            : 0;

        return [$late, $overtime];
    }
}
