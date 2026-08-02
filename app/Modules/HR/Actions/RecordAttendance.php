<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * ثبت یا ویرایش دستی **یک تردد** توسط ادمین/حسابدار.
 *
 * این مسیر عمداً هیچ محدودیت زمانی ندارد: ادمین مسئول رسیدگی به موارد استثناست
 * (فراموشی ثبت خروج، غیبت اشتباه ثبت‌شده، اصلاح ساعت)، پس باید بتواند هر تاریخی
 * را ثبت یا اصلاح کند.
 *
 * مسیر خودِ کارمند از این Action عبور نمی‌کند — PunchAttendance جداست و زمانش
 * را فقط از سرور می‌گیرد. تفکیک عمدی است: تا وقتی این متد پارامتر زمان می‌گیرد،
 * هر caller ای می‌تواند زمان دلخواه بفرستد، و آن اختیار فقط باید دست ادمین باشد.
 */
class RecordAttendance
{
    /**
     * @param  array{check_in_at?: ?string, check_out_at?: ?string}  $times  فقط کلیدهای واقعاً ارسالی روی رکورد اعمال می‌شوند
     * @param  Attendance|null  $target  پر = ویرایش همان تردد، خالی = تردد جدید
     */
    public function handle(
        Employee $employee,
        string $attendanceDate,
        array $times,
        User $actor,
        ?Attendance $target = null
    ): Attendance {
        Gate::forUser($actor)->authorize('recordAny', [Attendance::class, $employee->owner_company_id]);

        Validator::make(['attendance_date' => $attendanceDate], [
            'attendance_date' => ['required', 'date'],
        ])->validate();

        return DB::transaction(function () use ($employee, $attendanceDate, $times, $actor, $target) {
            // هدف صریح است، نه استنتاج‌شده از «کارمند + تاریخ»: از وقتی هر روز
            // می‌تواند چند تردد داشته باشد، «رکورد آن روز» دیگر یکتا نیست و
            // update-or-create روی تاریخ، تردد اشتباهی را بازنویسی می‌کرد.
            $attendance = $target ?? new Attendance([
                'employee_id' => $employee->id,
                'attendance_date' => $attendanceDate,
            ]);

            if ($target) {
                $attendance->attendance_date = $attendanceDate;
            }

            if (array_key_exists('check_in_at', $times)) {
                $attendance->check_in_at = $times['check_in_at'];
            }

            if (array_key_exists('check_out_at', $times)) {
                $attendance->check_out_at = $times['check_out_at'];
            }

            $attendance->owner_company_id = $employee->owner_company_id;

            // recorded_by یعنی «چه کسی این رکورد را اولین بار ثبت کرد» و با
            // ویرایش عوض نمی‌شود. اگر با هر ویرایش بازنویسی می‌شد، ویرایش ادمین
            // روی یک تردد self آن را به admin برمی‌گرداند و دیگر معلوم نبود
            // رکورد اصالتاً توسط خودِ کارمند ثبت شده.
            if (! $attendance->exists) {
                $attendance->recorded_by = RecordedBy::Admin;
                $attendance->created_by_user_id = $actor->id;
            } else {
                $attendance->updated_by_user_id = $actor->id;
            }

            $attendance->save();

            return $attendance;
        });
    }
}
