@props(['minutes'])

{{--
    اختلاف کارکرد یک روز نسبت به روز کاری استاندارد، در یک نمایش واحد.

    چون از Session 6.5 محاسبه واحد شد، کسری و اضافه‌کاری هرگز هم‌زمان غیرصفر
    نیستند — پس یک عدد علامت‌دار همه‌چیز را می‌گوید:
      مثبت = اضافه‌کاری، منفی = کسری، صفر = دقیقاً یک روز کاری کامل.
--}}

@php
    $value = (int) $minutes;
    $hours = intdiv(abs($value), 60);
    $remainingMinutes = abs($value) % 60;

    $label = match (true) {
        $hours > 0 && $remainingMinutes > 0 => \App\Support\Farsi::toDigits($hours).' ساعت و '.\App\Support\Farsi::toDigits($remainingMinutes).' دقیقه',
        $hours > 0 => \App\Support\Farsi::toDigits($hours).' ساعت',
        default => \App\Support\Farsi::toDigits($remainingMinutes).' دقیقه',
    };

    $sign = match (true) {
        $value > 0 => '+',
        $value < 0 => '−',
        default => '',
    };

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
@endphp

<x-badge
    value="{{ $value === 0 ? '۰' : $sign.' '.$label }}"
    class="{{ $badgeClass }}"
    :tooltip="$tooltip"
/>
