<?php

use Livewire\Component;

new class extends Component {
    public int $price = 1250000;

    public string $demoTime = '';
}; ?>

<div>
    <x-header title="نمایش تم" subtitle="آزمایش رنگ، آیکون، لوگو و راست‌چین بودن" separator />

    <x-card title="کارت نمونه" shadow>
        <div class="flex items-center gap-3">
            <x-icon :name="theme_icon('order')" class="w-6 text-primary" />
            <span>سفارش‌ها</span>
        </div>

        <div class="mt-4">
            قیمت نمونه: <span class="font-bold text-primary">@toman($price)</span>
        </div>

        <x-slot:actions>
            <x-button label="دکمه اصلی" class="btn-primary" :icon="theme_icon('save')" />
        </x-slot:actions>
    </x-card>

    {{-- انتخابگر ساعت اختصاصی — Mary UI معادلی ندارد، پس اینجا هم نمایش داده
         می‌شود تا راست‌چینی و هماهنگی‌اش با تم قابل بررسی باشد. --}}
    <x-card title="انتخابگر ساعت" shadow class="mt-4">
        <div class="max-w-xs">
            <x-time-picker field="demoTime" label="ساعت نمونه" :icon="theme_icon('hourly')" />
        </div>

        <div class="mt-4 text-sm text-base-content/70">
            مقدار انتخاب‌شده:
            <span class="font-semibold">{{ $demoTime !== '' ? \App\Support\Farsi::toDigits($demoTime) : '—' }}</span>
        </div>
    </x-card>
</div>
