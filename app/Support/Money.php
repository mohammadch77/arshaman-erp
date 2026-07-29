<?php

namespace App\Support;

/**
 * محاسبات پولی با bcmath روی رشته — هرگز float (CLAUDE.md بند ۳).
 *
 * چرا رشته: float نمی‌تواند 0.07 را دقیق نگه دارد؛ در جمع چند فیش، خطای
 * باینری انباشته می‌شود و گزارش تجمیعی هلدینگ با جمع دستی نمی‌خواند.
 */
class Money
{
    /** دقت نهایی ذخیره‌سازی — برابر DECIMAL(18,2). */
    public const SCALE = 2;

    /** دقت میانی محاسبه؛ فقط در پایان به SCALE گرد می‌شود. */
    public const WORKING_SCALE = 8;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::WORKING_SCALE);
    }

    public static function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, self::WORKING_SCALE);
    }

    public static function multiply(string $a, string $b): string
    {
        return bcmul($a, $b, self::WORKING_SCALE);
    }

    public static function divide(string $a, string $b): string
    {
        return bcdiv($a, $b, self::WORKING_SCALE);
    }

    public static function isGreaterThan(string $a, string $b): bool
    {
        return bccomp($a, $b, self::WORKING_SCALE) > 0;
    }

    /**
     * گردکردن نیم‌به‌بالا. bcmath خودش فقط truncate می‌کند، پس گردکردن باید
     * صریح باشد وگرنه هر مبلغ همیشه به سمت پایین می‌افتد.
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        $shifted = bccomp($value, '0', self::WORKING_SCALE) >= 0
            ? bcadd($value, $half, $scale + 1)
            : bcsub($value, $half, $scale + 1);

        return bcadd($shifted, '0', $scale);
    }
}
