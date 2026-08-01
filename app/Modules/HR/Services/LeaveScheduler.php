<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * منطق مشترک ثبت و ویرایش درخواست مرخصی.
 *
 * چرا سرویس جدا: `RequestLeave` و `UpdateLeaveRequest` هر دو باید دقیقاً همان
 * اعتبارسنجی، همان قاعده تداخل و همان محاسبه روز/ساعت را اجرا کنند. اگر این
 * منطق در دو Action کپی می‌شد، اولین جایی بود که با تغییر بعدی از هم دور
 * می‌افتادند — و ویرایش، قاعده‌ای را دور می‌زد که ثبت رعایتش می‌کند.
 *
 * authorization اینجا **نیست** و عمداً در خود Action ها می‌ماند (CLAUDE.md بند ۹).
 */
class LeaveScheduler
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'leave_type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
            // فقط برای نوع ساعتی معنا دارند؛ اعتبارسنجی وابسته به نوع در
            // assertHourlyShape انجام می‌شود چون به مقدار leave_type نیاز دارد.
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * قواعدی که فقط با دانستن نوع مرخصی قابل بررسی‌اند.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertHourlyShape(array $data): void
    {
        if (! $this->isHourly($data)) {
            return;
        }

        if (blank($data['start_time'] ?? null) || blank($data['end_time'] ?? null)) {
            throw ValidationException::withMessages([
                'start_time' => 'برای مرخصی ساعتی، ساعت شروع و پایان الزامی است.',
            ]);
        }

        // مرخصی ساعتی ذاتاً یک‌روزه است. اگر بازه چندروزه اجازه داده می‌شد،
        // معلوم نبود ساعت شروع/پایان به کدام روز مربوط است.
        if ($data['start_date'] !== $data['end_date']) {
            throw ValidationException::withMessages([
                'end_date' => 'مرخصی ساعتی فقط برای یک روز ثبت می‌شود.',
            ]);
        }

        if (Carbon::parse($data['end_time'])->lte(Carbon::parse($data['start_time']))) {
            throw ValidationException::withMessages([
                'end_time' => 'ساعت پایان باید بعد از ساعت شروع باشد.',
            ]);
        }
    }

    /**
     * یک روز نباید هم‌زمان زیر دو مرخصی «زنده» (در انتظار یا تأییدشده) برود —
     * وگرنه دو تصمیم تأیید متناقض روی همان روز ممکن می‌شود و مصرف‌کننده‌های این
     * جدول (جمع ماهانه، کسر حقوق) داده دوبار-شمرده می‌گیرند.
     *
     * استثنای مرخصی ساعتی: دو مرخصی ساعتی در یک روز ولی در ساعت‌های جدا (مثلاً
     * ۹ تا ۱۰ و ۱۴ تا ۱۵) تداخل واقعی ندارند و باید مجاز باشند. تداخل فقط وقتی
     * است که بازه ساعتی‌شان هم روی هم بیفتد.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $ignoreLeaveId  هنگام ویرایش، خودِ رکورد نباید با خودش تداخل بگیرد
     */
    public function assertNoOverlap(Employee $employee, array $data, ?string $ignoreLeaveId = null): void
    {
        // withoutGlobalScope('owner_company') و نه withoutGlobalScopes(): دومی
        // scope حذف نرم را هم برمی‌داشت و یک درخواست حذف‌شده باز هم مانع ثبت
        // درخواست جدید روی همان بازه می‌شد.
        // whereDate و نه یک شرط خام: ستون‌های تاریخ با cast تاریخِ Eloquent به‌شکل
        // کامل datetime ذخیره می‌شوند ('2026-08-03 00:00:00')، پس مقایسه رشته‌ای
        // با یک تاریخ خام ('2026-08-03') برای «کوچک‌تر یا مساوی» شکست می‌خورد.
        // نتیجه‌اش این بود که دو مرخصیِ **هم‌روز** هرگز تداخل نمی‌گرفتند — یعنی
        // یک کارمند می‌توانست دو مرخصی روی یک روز واحد ثبت کند.
        $candidates = Leave::withoutGlobalScope('owner_company')
            ->where('employee_id', $employee->id)
            ->whereIn('leave_status', [LeaveStatus::Pending, LeaveStatus::Approved])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->when($ignoreLeaveId, fn ($query) => $query->whereKeyNot($ignoreLeaveId))
            ->get();

        foreach ($candidates as $existing) {
            if ($this->collidesWith($data, $existing)) {
                throw ValidationException::withMessages([
                    'start_date' => 'این کارمند برای بخشی از همین بازه، مرخصی در انتظار یا تأییدشده دیگری دارد.',
                ]);
            }
        }
    }

    /**
     * تعداد روز و ساعت این درخواست.
     *
     * مرخصی ساعتی `days_count = 0` می‌گیرد و ساعتش در `hours_count` می‌نشیند —
     * نه کسر اعشاری از روز. دلیل کامل در migration مربوط
     * (2026_07_29_100012_add_hourly_fields_to_leaves_table.php) نوشته شده.
     *
     * @param  array<string, mixed>  $data
     * @return array{days_count: int, hours_count: string|null}
     */
    public function measure(Employee $employee, array $data): array
    {
        if ($this->isHourly($data)) {
            $minutes = Carbon::parse($data['start_time'])->diffInMinutes(Carbon::parse($data['end_time']));

            return [
                'days_count' => 0,
                'hours_count' => number_format($minutes / 60, 2, '.', ''),
            ];
        }

        $workCalendar = app(WorkCalendar::class);
        $daysCount = 0;

        for (
            $date = Carbon::parse($data['start_date']);
            $date->lte(Carbon::parse($data['end_date']));
            $date->addDay()
        ) {
            if ($workCalendar->isWorkday($date, $employee->owner_company_id)) {
                $daysCount++;
            }
        }

        return ['days_count' => $daysCount, 'hours_count' => null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function collidesWith(array $data, Leave $existing): bool
    {
        // اگر حتی یکی از دو طرف تمام‌روز باشد، هم‌پوشانی تاریخ کافی است.
        if (! $this->isHourly($data) || ! $existing->leave_type->isHourly()) {
            return true;
        }

        // هر دو ساعتی و روی همان روز: فقط اگر بازه ساعتی‌شان تلاقی کند.
        $newStart = Carbon::parse($data['start_time']);
        $newEnd = Carbon::parse($data['end_time']);
        $existingStart = Carbon::parse($existing->start_time);
        $existingEnd = Carbon::parse($existing->end_time);

        return $newStart->lt($existingEnd) && $newEnd->gt($existingStart);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function isHourly(array $data): bool
    {
        return ($data['leave_type'] ?? null) === LeaveType::Hourly->value;
    }
}
