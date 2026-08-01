<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Attendance;
use Illuminate\Support\Collection;

/**
 * محاسبه کارکرد در سطح **روز**، نه در سطح یک تردد.
 *
 * چرا سطح روز: از وقتی هر ردیف attendances یک تردد است، یک روز می‌تواند چند
 * ردیف داشته باشد (ورود صبح، خروج ظهر، ورود بعدازظهر، خروج عصر). کسری و
 * اضافه‌کاری فقط وقتی معنا دارند که همه ترددهای آن روز با هم جمع شوند و حاصل
 * با طول روز کاری استاندارد مقایسه شود.
 *
 * اگر هر ردیف جدا با ۴۸۰ دقیقه مقایسه می‌شد، دو تردد چهارساعته در یک روز
 * جمعاً ۴۸۰ دقیقه کسری می‌گرفتند — برای روزی که کامل کار شده.
 */
class AttendanceCalculator
{
    /**
     * مجموع دقیقه‌های کارکرد ترددهای **بسته** یک روز.
     *
     * @param  Collection<int, Attendance>  $punchesOfOneDay
     */
    public function workedMinutes(Collection $punchesOfOneDay): int
    {
        return (int) $punchesOfOneDay->sum(
            fn (Attendance $punch) => $punch->duration_minutes ?? 0
        );
    }

    /**
     * @param  Collection<int, Attendance>  $punchesOfOneDay
     */
    public function hasOpenPunch(Collection $punchesOfOneDay): bool
    {
        return $punchesOfOneDay->contains(fn (Attendance $punch) => $punch->isOpen());
    }

    /**
     * کسری و اضافه‌کاری یک روز.
     *
     *   مجموع کارکرد < روز کاری استاندارد → کسری = تفاوت،       اضافه‌کاری = ۰
     *   مجموع کارکرد > روز کاری استاندارد → اضافه‌کاری = تفاوت، کسری = ۰
     *
     * این دو عدد هرگز هم‌زمان غیرصفر نمی‌شوند.
     *
     * تا وقتی حتی یک تردد آن روز **باز** باشد، هر دو صفر می‌مانند: کارکرد روز
     * هنوز تمام نشده و هر عددی حدس است. بدون این قاعده، کارمندی که همین الان
     * سرِ کار است، برای «امروز» یک روز کامل کسری می‌گرفت.
     *
     * @param  Collection<int, Attendance>  $punchesOfOneDay
     * @return array{0: int, 1: int} [کسری، اضافه‌کاری]
     */
    public function minutesForDay(Collection $punchesOfOneDay): array
    {
        if ($punchesOfOneDay->isEmpty() || $this->hasOpenPunch($punchesOfOneDay)) {
            return [0, 0];
        }

        $difference = $this->workedMinutes($punchesOfOneDay) - $this->standardWorkdayMinutes();

        return $difference < 0
            ? [abs($difference), 0]
            : [0, $difference];
    }

    /**
     * اختلاف خالص روز با علامت: مثبت = اضافه‌کاری، منفی = کسری.
     *
     * @param  Collection<int, Attendance>  $punchesOfOneDay
     */
    public function balanceForDay(Collection $punchesOfOneDay): int
    {
        [$shortfall, $overtime] = $this->minutesForDay($punchesOfOneDay);

        return $overtime - $shortfall;
    }

    protected function standardWorkdayMinutes(): int
    {
        return (int) config('hr.standard_workday_minutes');
    }
}
