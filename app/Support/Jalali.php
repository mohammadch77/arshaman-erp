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
    /**
     * یک لحظه ذخیره‌شده (UTC) را به منطقه زمانی نمایش می‌برد.
     *
     * چرا لازم است: ذخیره همیشه UTC است، ولی «ساعت ۸ صبح» و «کدام روز» فقط به
     * وقت محلی معنا دارند. بدون این تبدیل، یک تردد ساعت ۱۱:۲۳ تهران به‌صورت
     * ۰۷:۵۳ نمایش داده می‌شد و یک تردد ساعت ۰۲:۰۰ بامداد، زیر تاریخ **دیروز**
     * می‌افتاد — هر دو به اندازه اختلاف تهران با UTC (۳ ساعت و ۳۰ دقیقه) غلط.
     */
    public static function local(Carbon|string|null $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return $carbon->setTimezone(config('app.display_timezone'));
    }

    /**
     * عکسِ local(): یک ساعتِ دیواریِ محلی که کاربر وارد کرده را به لحظه UTC
     * تبدیل می‌کند تا ذخیره شود.
     *
     * بدون این، ادمینی که در فرم «۰۸:۱۰» می‌نویسد، ۰۸:۱۰ **UTC** ذخیره می‌کرد
     * که به وقت تهران ۱۱:۴۰ است — یعنی عددی که نوشته و عددی که ذخیره شده یکی
     * نبودند.
     */
    public static function fromLocal(string $localDateTime): Carbon
    {
        return Carbon::parse($localDateTime, config('app.display_timezone'))
            ->setTimezone(config('app.timezone'));
    }

    /**
     * «امروز» به وقت محلی، نه به وقت UTC سرور.
     */
    public static function today(): Carbon
    {
        return Carbon::now(config('app.display_timezone'))->startOfDay();
    }

    /**
     * تاریخ تقویمی محلیِ یک لحظه (Y-m-d میلادی) — برای ستون‌های DATE که یک روز
     * کاری را نشان می‌دهند، نه یک لحظه.
     */
    public static function localDateString(Carbon|string|null $date): ?string
    {
        return self::local($date)?->toDateString();
    }

    /**
     * یک ستون DATE را در نیمه‌شبِ همان روز به وقت محلی می‌نشاند.
     *
     * تفاوتش با local(): آن یک **لحظه** را بین منطقه‌های زمانی جابه‌جا می‌کند،
     * ولی ستون‌های DATE (تاریخ استخدام، پایان قرارداد، شروع مرخصی) اصلاً لحظه
     * نیستند — یک روز تقویمی‌اند و نباید جابه‌جا شوند. اگر با local() تبدیل
     * می‌شدند، «۱ مرداد» به «۳۱ تیر ساعت ۲۰:۳۰» تبدیل می‌شد.
     *
     * کاربردش مقایسه روز-با-روز است، جایی که اختلاف ساعت نباید مرز را جابه‌جا کند.
     */
    public static function calendarDay(Carbon|string|null $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return Carbon::parse($dateString, config('app.display_timezone'))->startOfDay();
    }

    /**
     * ساعت محلی به شکل ۰۸:۳۰ با ارقام فارسی.
     */
    public static function toDisplayTime(Carbon|string|null $date): ?string
    {
        $local = self::local($date);

        return $local === null ? null : Farsi::toDigits($local->format('H:i'));
    }

    public static function toDisplay(Carbon|string|null $date): ?string
    {
        $local = self::local($date);

        if ($local === null) {
            return null;
        }

        return Farsi::toDigits(Jalalian::fromCarbon($local)->format('Y/m/d'));
    }

    /**
     * تاریخ و ساعت با هم — برای مهرهای زمانی مثل «آخرین محاسبه».
     */
    public static function toDisplayDateTime(Carbon|string|null $date): ?string
    {
        $local = self::local($date);

        if ($local === null) {
            return null;
        }

        return Farsi::toDigits(Jalalian::fromCarbon($local)->format('Y/m/d').' '.$local->format('H:i'));
    }

    /**
     * @return array{year: ?int, month: ?int, day: ?int}
     */
    public static function toJalaliParts(Carbon|string|null $date): array
    {
        $local = self::local($date);

        if ($local === null) {
            return ['year' => null, 'month' => null, 'day' => null];
        }

        $jalali = Jalalian::fromCarbon($local);

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
