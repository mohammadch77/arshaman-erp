<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\ExchangeRate;
use Carbon\Carbon;
use RuntimeException;

/**
 * تبدیل تاریخ به نرخ روز — طبق طراحی سند (جدول ۳): «نرخ برای تاریخی که نرخ
 * مستقیم ندارد، آخرین نرخ قبلی را برمی‌گرداند» (مثلاً روزهای تعطیل بدون نرخ ثبت‌شده).
 */
class ExchangeRateResolver
{
    /**
     * @return string مقدار decimal به‌صورت رشته (بدون float، طبق CLAUDE.md بند ۳)
     */
    public function rate(string $currencyId, Carbon $date): string
    {
        $rate = ExchangeRate::query()
            ->where('currency_id', $currencyId)
            ->where('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->first();

        if (! $rate) {
            throw new RuntimeException(
                "هیچ نرخ ثبت‌شده‌ای برای این ارز تا تاریخ {$date->toDateString()} یافت نشد."
            );
        }

        return $rate->rate_to_toman;
    }
}
