<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Holiday;
use Carbon\Carbon;

/**
 * جایگزین سیستم حضور و غیاب واقعی برای تشخیص روز کاری —
 * چون سیستم زمان‌پرداز فعلی امکان اتصال ندارد (docs/PROJECT_02_HR.md).
 */
class WorkCalendar
{
    /**
     * جمعه یا تعطیل رسمی (سراسری یا مخصوص شرکت) → false.
     */
    public function isWorkday(Carbon $date, ?string $companyId = null): bool
    {
        if ($date->isFriday()) {
            return false;
        }

        return ! $this->isHoliday($date, $companyId);
    }

    protected function isHoliday(Carbon $date, ?string $companyId): bool
    {
        return Holiday::query()
            ->where(function ($query) use ($companyId) {
                $query->whereNull('owner_company_id');

                if ($companyId !== null) {
                    $query->orWhere('owner_company_id', $companyId);
                }
            })
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    $q->where('is_recurring_yearly', true)
                        ->whereMonth('holiday_date', $date->month)
                        ->whereDay('holiday_date', $date->day);
                })->orWhere(function ($q) use ($date) {
                    $q->where('is_recurring_yearly', false)
                        ->whereDate('holiday_date', $date->toDateString());
                });
            })
            ->exists();
    }
}
