<?php

namespace App\Support;

use App\Modules\Catalog\Enums\UnitOfMeasure;
use App\Modules\Core\Models\Currency;

class Farsi
{
    protected const DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    protected const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    protected const ARABIC_INDIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    protected const ENGLISH_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function toDigits(int|string $input): string
    {
        return preg_replace_callback('/\d/', fn ($m) => self::DIGITS[$m[0]], (string) $input);
    }

    /**
     * فارسی/عربی → لاتین، برعکس toDigits — روی فیلدهای عددی فرم (تعداد،
     * بهای واحد و ...) لازم است: کیبورد فارسی ویندوز رقم‌های ۰-۹ را تایپ
     * می‌کند، ولی قوانین اعتبارسنجی PHP (`numeric`) و bcmath این ارقام را
     * عدد نمی‌شناسند — کاربر رقمی تایپ می‌کند که از دیدش «پر شده» است ولی
     * سرور آن را نامعتبر/خالی می‌بیند. این متد قبل از اعتبارسنجی روی چنین
     * فیلدهایی صدا زده می‌شود تا ورودی همیشه لاتین باشد.
     */
    public static function toEnglishDigits(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $input = str_replace(self::PERSIAN_DIGITS, self::ENGLISH_DIGITS, $input);

        return str_replace(self::ARABIC_INDIC_DIGITS, self::ENGLISH_DIGITS, $input);
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
     * مثل toToman ولی واحد پول واقعی محصول/سفارش را نمایش می‌دهد، نه همیشه
     * تومان — بند ۵.۳/۳ CLAUDE.md: currency_id سطح محصول تعیین‌کننده است.
     * $currency خالی یعنی ارز پایه هلدینگ (تومان)، دقیقاً همان قرارداد
     * nullable بودن products.currency_id/orders.currency_id.
     */
    public static function toMoney(float|int|string $amount, ?Currency $currency = null): string
    {
        if ($currency === null) {
            return self::toToman($amount);
        }

        $formatted = is_string($amount)
            ? self::groupDecimalString($amount)
            : number_format($amount);

        $unit = $currency->symbol !== null && $currency->symbol !== '' ? $currency->symbol : $currency->code;

        return self::toDigits($formatted).' '.$unit;
    }

    /**
     * نمایش تعداد بر اساس واحد اندازه‌گیری محصول — quantity_on_hand در
     * دیتابیس همیشه DECIMAL(18,4) می‌ماند (برای دقت لازم است)، ولی نمایش آن
     * برای واحدهای شمارشی (عدد) با اعشار گمراه‌کننده است («۳۰٫۰۰۰۰» به‌جای
     * «۳۰»). برای واحدهای وزنی/حجمی تا ۲ رقم اعشار (بدون صفرهای اضافه) نشان
     * داده می‌شود.
     */
    public static function formatQuantity(float|int|string $quantity, ?UnitOfMeasure $unit = null): string
    {
        $decimals = ($unit === null || $unit === UnitOfMeasure::Piece) ? 0 : 2;
        $rounded = Money::round((string) $quantity, $decimals);

        return self::toDigits(self::trimTrailingZeros($rounded));
    }

    protected static function trimTrailingZeros(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
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
