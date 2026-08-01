<?php

use App\Support\Farsi;

/*
|--------------------------------------------------------------------------
| نمایش خوانای مدت زمان
|--------------------------------------------------------------------------
|
| تنها نقطه تولید این متن در پروژه Farsi::duration است — کامپوننت اختلاف
| کارکرد، لاگ تردد، و مرخصی ساعتی (هر دو پنل) همگی از همین عبور می‌کنند.
*/

it('shows only minutes when under an hour', function () {
    expect(Farsi::duration(46))->toBe('۴۶ دقیقه');
    expect(Farsi::duration(1))->toBe('۱ دقیقه');
});

it('shows only hours when there is no remainder', function () {
    // نکته اصلی: «۲ ساعت» و نه «۲ ساعت و ۰ دقیقه».
    expect(Farsi::duration(120))->toBe('۲ ساعت');
    expect(Farsi::duration(60))->toBe('۱ ساعت');
});

it('shows both parts when the duration is mixed', function () {
    expect(Farsi::duration(75))->toBe('۱ ساعت و ۱۵ دقیقه');
    expect(Farsi::duration(505))->toBe('۸ ساعت و ۲۵ دقیقه');
});

it('shows zero as a real value, not an empty string', function () {
    // در جدول، سلول خالی یعنی «داده نداریم» ولی صفر یعنی «صفر بود».
    expect(Farsi::duration(0))->toBe('۰ دقیقه');
});

it('marks a negative duration with a sign', function () {
    expect(Farsi::duration(-30))->toBe('−۳۰ دقیقه');
    expect(Farsi::duration(-90))->toBe('−۱ ساعت و ۳۰ دقیقه');
});

it('converts decimal hours back to the exact original minutes', function () {
    // ۴۶ دقیقه با دو رقم اعشار ۰٫۷۷ ذخیره می‌شود؛ برگشتش باید دقیقاً ۴۶ شود،
    // نه ۴۶٫۲ و نه ۴۵.
    expect(Farsi::durationFromHours('0.77'))->toBe('۴۶ دقیقه');
    expect(Farsi::durationFromHours('2.00'))->toBe('۲ ساعت');
    expect(Farsi::durationFromHours('1.25'))->toBe('۱ ساعت و ۱۵ دقیقه');
});

it('round-trips every whole minute of a working day through decimal hours', function () {
    // ضامن اینکه گردکردن دو-رقمی هیچ دقیقه‌ای را جابه‌جا نکند.
    for ($minutes = 1; $minutes <= 480; $minutes++) {
        $stored = number_format($minutes / 60, 2, '.', '');

        expect(Farsi::durationFromHours($stored))
            ->toBe(Farsi::duration($minutes), "minute {$minutes} drifted (stored as {$stored})");
    }
});

it('treats a null hours_count as zero rather than crashing', function () {
    expect(Farsi::durationFromHours(null))->toBe('۰ دقیقه');
});
