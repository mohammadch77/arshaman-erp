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
