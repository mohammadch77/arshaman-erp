<?php

namespace App\Support;

class Farsi
{
    protected const DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public static function toDigits(int|string $input): string
    {
        return preg_replace_callback('/\d/', fn ($m) => self::DIGITS[$m[0]], (string) $input);
    }

    public static function toToman(float|int $amount): string
    {
        return self::toDigits(number_format($amount)).' تومان';
    }
}
