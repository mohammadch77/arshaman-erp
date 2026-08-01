<?php

namespace App\Support;

class Farsi
{
    protected const DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public static function toDigits(int|string $input): string
    {
        return preg_replace_callback('/\d/', fn ($m) => self::DIGITS[$m[0]], (string) $input);
    }

    /**
     * مبالغ decimal از دیتابیس به‌صورت رشته می‌آیند. اگر رشته را به float تبدیل
     * کنیم، همان چیزی را که CLAUDE.md بند ۳ منع کرده وارد مسیر نمایش کرده‌ایم و
     * مبالغ بزرگ (ریال) می‌توانند در ارقام پایانی جابه‌جا شوند. پس رشته عددی
     * بدون تبدیل به float گروه‌بندی می‌شود.
     */
    public static function toToman(float|int|string $amount): string
    {
        $formatted = is_string($amount)
            ? self::groupDecimalString($amount)
            : number_format($amount);

        return self::toDigits($formatted).' تومان';
    }

    /**
     * مدت زمان به شکل خوانا: «۴۶ دقیقه»، «۲ ساعت»، «۱ ساعت و ۱۵ دقیقه».
     *
     * تنها نقطه تولید این متن در کل پروژه — قبلاً همین منطق در سه جا تکرار شده
     * بود (کامپوننت اختلاف کارکرد، لاگ تردد، و مرخصی ساعتی) و اولین جایی بود که
     * با تغییر بعدی از هم دور می‌افتادند.
     *
     * «۰ دقیقه» عمداً برای صفر برگردانده می‌شود، نه رشته خالی: در جدول، سلول
     * خالی یعنی «داده نداریم» ولی صفر یعنی «صفر بود» — دو معنای متفاوت.
     */
    public static function duration(int $minutes): string
    {
        $isNegative = $minutes < 0;
        $absolute = abs($minutes);
        $hours = intdiv($absolute, 60);
        $remaining = $absolute % 60;

        $text = match (true) {
            $hours > 0 && $remaining > 0 => self::toDigits($hours).' ساعت و '.self::toDigits($remaining).' دقیقه',
            $hours > 0 => self::toDigits($hours).' ساعت',
            default => self::toDigits($remaining).' دقیقه',
        };

        return $isNegative ? '−'.$text : $text;
    }

    /**
     * همان خروجی، ولی از ساعتِ اعشاری (ستون leaves.hours_count).
     *
     * تبدیل با float انجام می‌شود و این استثنای قانون «هرگز float» بند ۳ نیست:
     * آن قانون درباره **مبالغ** است. اینجا فقط یک مدت کوچک برای نمایش است و
     * چون hours_count با دو رقم اعشار از روی دقیقه‌های صحیح ساخته شده،
     * گردکردن دقیقاً همان عدد صحیح اولیه را برمی‌گرداند (۴۶ دقیقه → ۰٫۷۷ → ۴۶).
     */
    public static function durationFromHours(float|int|string|null $hours): string
    {
        return self::duration((int) round(((float) $hours) * 60));
    }

    protected static function groupDecimalString(string $amount): string
    {
        $isNegative = str_starts_with(trim($amount), '-');
        $integerPart = explode('.', ltrim(trim($amount), '+-'))[0];
        $integerPart = ltrim($integerPart, '0');

        if ($integerPart === '') {
            return '0';
        }

        $grouped = strrev(implode(',', str_split(strrev($integerPart), 3)));

        return $isNegative ? '-'.$grouped : $grouped;
    }
}
