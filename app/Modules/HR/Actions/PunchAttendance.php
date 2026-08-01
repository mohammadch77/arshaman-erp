<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\PunchDirection;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * ثبت تردد توسط خودِ کارمند — «ثبت ورود» و «ثبت خروج».
 *
 * **زمان از سرور گرفته می‌شود و هیچ راهی برای فرستادنش از بیرون وجود ندارد.**
 * این یک تضمین ساختاری است، نه یک بررسی زمان اجرا: امضای این متد اصلاً پارامتر
 * تاریخ یا ساعت ندارد، پس هیچ caller ای — نه کامپوننت Livewire (که خصوصیات
 * public اش از سمت مرورگر قابل دستکاری است)، نه کنسول، نه کد آینده — نمی‌تواند
 * زمان دلخواه بنویسد. ثبت دستی برای تاریخ‌های گذشته کار ادمین است و مسیر جدای
 * خودش را دارد (RecordAttendance).
 *
 * ماشین وضعیت تردد:
 *
 *   بدون تردد باز ──ورود──► تردد باز ──خروج──► تردد بسته
 *          ▲                     │                  │
 *          └─────────────────────┘◄─────────────────┘
 *                    (چرخه در یک روز تکرارپذیر است)
 *
 * ترنزیشن‌های تعریف‌نشده رد می‌شوند (CLAUDE.md بند ۶): ورود وقتی تردد باز هست،
 * و خروج وقتی نیست.
 */
class PunchAttendance
{
    public function handle(Employee $employee, PunchDirection $direction, User $actor): Attendance
    {
        // authorize داخل خود Action — CLAUDE.md بند ۹.
        Gate::forUser($actor)->authorize('recordSelf', [Attendance::class, $employee]);

        return DB::transaction(fn () => $direction === PunchDirection::In
            ? $this->punchIn($employee, $actor)
            : $this->punchOut($employee, $actor));
    }

    protected function punchIn(Employee $employee, User $actor): Attendance
    {
        $open = $this->openPunch($employee);

        if ($open) {
            // خروج فراموش‌شده عمداً خودکار بسته نمی‌شود: بستن خودکار یعنی ساختن
            // یک ساعت خروج که هرگز اتفاق نیفتاده، و آن عدد مستقیم وارد محاسبه
            // حقوق می‌شود. اصلاحش کار ادمین است.
            throw ValidationException::withMessages([
                'check_in_at' => sprintf(
                    'شما یک تردد باز از %s دارید. ابتدا خروج بزنید؛ اگر خروج آن روز را فراموش کرده‌اید، با مدیر یا حسابدار هماهنگ کنید.',
                    Jalali::toDisplay($open->check_in_at),
                ),
            ]);
        }

        $now = Carbon::now();

        $attendance = new Attendance([
            'employee_id' => $employee->id,
            // روزِ ورود مبنا است، نه روز خروج: یک شیفت یک رکورد است و کارکردش
            // به روزی تعلق می‌گیرد که شروع شده — وگرنه یک شیفت شبانه بین دو روز
            // (و گاهی دو ماه) تکه‌تکه می‌شد.
            //
            // تاریخ **محلی** و نه UTC: attendance_date یک روز کاری است، نه یک
            // لحظه. با تاریخ UTC، ورود ساعت ۰۱:۰۰ بامداد تهران زیر تاریخ دیروز
            // ثبت می‌شد و به جمع ماهانه و حقوق روز اشتباه می‌رفت.
            'attendance_date' => Jalali::localDateString($now),
            // خودِ لحظه همچنان UTC ذخیره می‌شود — بند ۳ CLAUDE.md.
            'check_in_at' => $now,
        ]);

        $attendance->owner_company_id = $employee->owner_company_id;
        $attendance->recorded_by = RecordedBy::SelfService;
        $attendance->created_by_user_id = $actor->id;
        $attendance->save();

        return $attendance;
    }

    protected function punchOut(Employee $employee, User $actor): Attendance
    {
        $open = $this->openPunch($employee);

        if (! $open) {
            throw ValidationException::withMessages([
                'check_out_at' => 'تردد بازی ندارید. ابتدا ورود بزنید.',
            ]);
        }

        $open->check_out_at = Carbon::now();
        $open->updated_by_user_id = $actor->id;
        $open->save();

        return $open;
    }

    /**
     * تردد باز کارمند، بدون قید تاریخ.
     *
     * بدون قید تاریخ عمدی است: شیفتی که ۲۳:۰۰ شروع شده و ۰۱:۰۰ بامداد تمام
     * می‌شود، همان تردد باز دیروز را می‌بندد. اگر «فقط امروز» می‌گشتیم، کارمند
     * شیفت شب هرگز نمی‌توانست خروج بزند.
     *
     * lockForUpdate برای مسابقه دو کلیک سریع؛ لایه دوم همان تضمین، ایندکس یکتای
     * uq_attendance_single_open_punch در سطح دیتابیس است.
     */
    protected function openPunch(Employee $employee): ?Attendance
    {
        return Attendance::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereNull('check_out_at')
            ->orderByDesc('check_in_at')
            ->lockForUpdate()
            ->first();
    }
}
