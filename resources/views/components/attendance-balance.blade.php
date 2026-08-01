@props(['minutes'])

{{--
    اختلاف کارکرد یک روز نسبت به روز کاری استاندارد، در یک نمایش واحد.

    چون از Session 6.5 محاسبه واحد شد، کسری و اضافه‌کاری هرگز هم‌زمان غیرصفر
    نیستند — پس یک عدد علامت‌دار همه‌چیز را می‌گوید:
      مثبت = اضافه‌کاری، منفی = کسری، صفر = دقیقاً یک روز کاری کامل.

    متن مدت از Farsi::duration می‌آید (تنها نقطه تولید این متن در پروژه).
--}}

@php
    $value = (int) $minutes;

    $badgeClass = match (true) {
        $value > 0 => 'badge-success',
        $value < 0 => 'badge-error',
        default => 'badge-ghost',
    };

    $tooltip = match (true) {
        $value > 0 => 'اضافه‌کاری',
        $value < 0 => 'کسری کارکرد',
        default => 'دقیقاً یک روز کاری کامل',
    };

    // علامت منفی را خود Farsi::duration می‌گذارد؛ مثبت را اینجا اضافه می‌کنیم.
    $label = $value > 0
        ? '+ '.\App\Support\Farsi::duration($value)
        : \App\Support\Farsi::duration($value);
@endphp

<x-badge :value="$label" class="{{ $badgeClass }}" :tooltip="$tooltip" />
