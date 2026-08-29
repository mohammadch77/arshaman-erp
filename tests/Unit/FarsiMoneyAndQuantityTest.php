<?php

use App\Modules\Catalog\Enums\UnitOfMeasure;
use App\Support\Farsi;

/*
|--------------------------------------------------------------------------
| نمایش مبلغ بدون ارز + نمایش تعداد بر اساس واحد اندازه‌گیری
|--------------------------------------------------------------------------
|
| Farsi::toMoney جایگزین استفاده هاردکد از toToman برای مبالغ محصول/سفارش
| است — وقتی ارز محصول خالی است (تومان)، همان toToman قبلی را می‌دهد. تست‌های
| ارز واقعی (نیازمند دیتابیس) در tests/Feature/Core/FarsiMoneyDisplayTest.php هستند.
*/

it('formats an amount without a currency as toman, same as toToman', function () {
    expect(Farsi::toMoney('150000'))->toBe(Farsi::toToman('150000'));
});

it('formats a piece-unit quantity as a whole number without decimals', function () {
    expect(Farsi::formatQuantity('30.0000', UnitOfMeasure::Piece))->toBe('۳۰');
    expect(Farsi::formatQuantity('30.0000', null))->toBe('۳۰');
});

it('formats a kilogram/liter quantity with up to two decimals, trimming trailing zeros', function () {
    expect(Farsi::formatQuantity('2.5000', UnitOfMeasure::Kilogram))->toBe('۲.۵');
    expect(Farsi::formatQuantity('3.0000', UnitOfMeasure::Liter))->toBe('۳');
    expect(Farsi::formatQuantity('2.4567', UnitOfMeasure::Kilogram))->toBe('۲.۴۶');
});

/*
|--------------------------------------------------------------------------
| نرمال‌سازی ارقام فارسی/عربی ورودی به لاتین
|--------------------------------------------------------------------------
|
| کیبورد فارسی رقم ۰-۹ تایپ می‌کند که PHP is_numeric()/bcmath آن را عدد
| نمی‌شناسد — کاربر فیلدی را «پر» می‌بیند که سرور رد می‌کند («این فیلد باید
| عدد باشد») و اگر همان مقدار قرار بود رد یک قید کسب‌وکاری (مثل «موجودی
| کافی نیست») شود، آن قید هرگز اجرا نمی‌شود چون اعتبارسنجی numeric زودتر رد
| کرده. کشف شد با بازدید بصری واقعی روی StockMovementForm.
*/
it('converts persian digits to english', function () {
    expect(Farsi::toEnglishDigits('۱۵'))->toBe('15');
    expect(Farsi::toEnglishDigits('۱۲.۵۰'))->toBe('12.50');
});

it('converts arabic-indic digits to english', function () {
    expect(Farsi::toEnglishDigits('١٥'))->toBe('15');
});

it('leaves already-english digits and null untouched', function () {
    expect(Farsi::toEnglishDigits('100'))->toBe('100');
    expect(Farsi::toEnglishDigits(null))->toBeNull();
    expect(Farsi::toEnglishDigits(''))->toBe('');
});
