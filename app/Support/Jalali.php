<?php

namespace App\Support;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * پل بین تاریخ ذخیره‌شده (UTC/میلادی در دیتابیس) و ورودی/نمایش شمسی —
 * طبق بخش ۳ CLAUDE.md: «ذخیره UTC، نمایش و ورودی شمسی».
 */
class Jalali
{
    public static function toDisplay(Carbon|string|null $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return Farsi::toDigits(Jalalian::fromCarbon($carbon)->format('Y/m/d'));
    }

    /**
     * @return array{year: ?int, month: ?int, day: ?int}
     */
    public static function toJalaliParts(Carbon|string|null $date): array
    {
        if ($date === null || $date === '') {
            return ['year' => null, 'month' => null, 'day' => null];
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $jalali = Jalalian::fromCarbon($carbon);

        return ['year' => $jalali->getYear(), 'month' => $jalali->getMonth(), 'day' => $jalali->getDay()];
    }

    /**
     * سال/ماه/روز شمسی را به رشته تاریخ میلادی (Y-m-d) برای ذخیره در دیتابیس تبدیل می‌کند.
     * روزهای نامعتبر برای ماه انتخابی (مثلاً ۳۱ برای مهر) به آخرین روز معتبر همان ماه محدود می‌شوند.
     */
    public static function toGregorian(int|string|null $year, int|string|null $month, int|string|null $day): ?string
    {
        if (! $year || ! $month || ! $day) {
            return null;
        }

        $year = (int) $year;
        $month = (int) $month;
        $day = min((int) $day, self::daysInMonth($year, $month));

        return (new Jalalian($year, $month, $day))->toCarbon()->toDateString();
    }

    public static function daysInMonth(int $year, int $month): int
    {
        return (new Jalalian($year, $month, 1))->getMonthDays();
    }

    /**
     * حداکثر روز معتبر برای یک ماه — حتی وقتی سال هنوز انتخاب نشده.
     * ماه‌های ۱ تا ۶: ۳۱ روز. ماه‌های ۷ تا ۱۱: ۳۰ روز. اسفند (۱۲): وابسته به کبیسه‌بودن سال؛
     * اگر سال هنوز انتخاب نشده، محافظه‌کارانه ۲۹ روز فرض می‌شود تا انتخاب سال.
     */
    public static function maxDayForMonth(int|string|null $year, int|string|null $month): int
    {
        if (! $month) {
            return 31;
        }

        $month = (int) $month;

        if ($year) {
            return self::daysInMonth((int) $year, $month);
        }

        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return 29;
    }

    public static function monthOptions(): array
    {
        $names = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return collect($names)->map(fn ($name, $month) => ['id' => $month, 'name' => $name])->values()->all();
    }

    public static function yearOptions(int $fromYearsAgo = 70, int $toYearsAhead = 5): array
    {
        $currentYear = Jalalian::now()->getYear();

        return collect(range($currentYear + $toYearsAhead, $currentYear - $fromYearsAgo))
            ->map(fn ($year) => ['id' => $year, 'name' => Farsi::toDigits($year)])
            ->values()
            ->all();
    }

    /**
     * فهرست روزهای معتبر یک ماه — reactive نسبت به ماه (و برای اسفند، سال) انتخاب‌شده.
     */
    public static function dayOptions(int|string|null $year = null, int|string|null $month = null): array
    {
        return collect(range(1, self::maxDayForMonth($year, $month)))
            ->map(fn ($day) => ['id' => $day, 'name' => Farsi::toDigits($day)])
            ->values()
            ->all();
    }
}
