@props([
    'field',
    'label' => null,
    'required' => false,
    'icon' => null,
    'placeholder' => 'انتخاب ساعت',
])

{{--
    انتخابگر ساعت و دقیقه — دو ستون قابل‌اسکرول، بدون تایپ عدد.

    چرا کامپوننت اختصاصی: Mary UI کلاک‌پیکر ندارد (x-datetime فقط یک input بومی
    با استایل است) و تنها انتخابگرش x-datepicker به flatpickr وابسته است که در
    پروژه نصب نیست. افزودن پکیج جدید طبق بند ۲ CLAUDE.md نیاز به تأیید دارد و
    برای یک فیلد، وزن اضافه‌ای بود. Alpine از قبل همراه Livewire هست.

    برچسب‌های فارسی در PHP با Farsi::toDigits ساخته و به Alpine داده می‌شوند —
    نه اینکه در JS یک نگاشت ارقام دوم نوشته شود. یعنی قاعده «اعداد فارسی» همچنان
    یک نقطه تولید دارد.

    مقدار نهایی همیشه به شکل H:i در همان property لایو‌وایر می‌نشیند، پس هیچ
    تغییری در سمت سرور لازم نیست.
--}}

@php
    $toOptions = fn (array $range) => collect($range)
        ->map(fn (int $n) => [
            'value' => sprintf('%02d', $n),
            'label' => \App\Support\Farsi::toDigits(sprintf('%02d', $n)),
        ])
        ->values()
        ->all();

    $hourOptions = $toOptions(range(0, 23));
    $minuteOptions = $toOptions(range(0, 59));
@endphp

<fieldset class="fieldset py-0">
    @if($label)
        <legend class="fieldset-legend mb-0.5">
            {{ $label }}
            @if($required)
                <span class="text-error">*</span>
            @endif
        </legend>
    @endif

    <div
        class="relative"
        x-data="{
            open: false,
            value: $wire.entangle(@js($field)),
            hours: @js($hourOptions),
            minutes: @js($minuteOptions),
            get hour() { return (this.value || '').split(':')[0] || ''; },
            get minute() { return (this.value || '').split(':')[1] || ''; },
            pickHour(h) { this.value = h + ':' + (this.minute || '00'); },
            pickMinute(m) { this.value = (this.hour || '00') + ':' + m; },
            labelOf(list, v) { const found = list.find(i => i.value === v); return found ? found.label : null; },
            display() {
                const h = this.labelOf(this.hours, this.hour);
                const m = this.labelOf(this.minutes, this.minute);
                return (h !== null && m !== null) ? h + ':' + m : null;
            },
            scrollToSelected() {
                this.$nextTick(() => {
                    this.$refs.hourList?.querySelector('[data-selected=true]')?.scrollIntoView({ block: 'center' });
                    this.$refs.minuteList?.querySelector('[data-selected=true]')?.scrollIntoView({ block: 'center' });
                });
            },
            toggle() { this.open = !this.open; if (this.open) this.scrollToSelected(); },
        }"
        @keydown.escape.window="open = false"
        @click.outside="open = false"
        wire:ignore.self
    >
        {{-- ماشه: خودش هیچ ورودی متنی نیست، پس عدد تایپ‌کردنی وجود ندارد. --}}
        <button
            type="button"
            class="input w-full flex items-center gap-2 text-start"
            :class="{ 'input-error': false }"
            @click="toggle()"
        >
            @if($icon)
                <x-icon :name="$icon" class="w-4 h-4 opacity-40 shrink-0" />
            @endif

            <span class="grow" x-text="display() ?? @js($placeholder)"
                  :class="display() === null ? 'opacity-50' : ''"></span>

            <x-icon :name="theme_icon('hourly')" class="w-4 h-4 opacity-40 shrink-0" />
        </button>

        {{-- بدون left/right: موقعیت‌دهی با start/end تا در RTL درست بنشیند. --}}
        <div
            x-show="open"
            x-transition.opacity
            x-cloak
            class="absolute z-20 mt-1 w-full rounded-box border border-base-300 bg-base-100 shadow-lg p-2"
        >
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <div class="text-xs text-base-content/60 text-center mb-1">ساعت</div>
                    <div class="max-h-48 overflow-y-auto" x-ref="hourList">
                        <template x-for="option in hours" :key="'h-' + option.value">
                            <button
                                type="button"
                                class="btn btn-sm btn-block mb-1"
                                :class="option.value === hour ? 'btn-primary' : 'btn-ghost'"
                                :data-selected="option.value === hour"
                                x-text="option.label"
                                @click="pickHour(option.value)"
                            ></button>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="text-xs text-base-content/60 text-center mb-1">دقیقه</div>
                    <div class="max-h-48 overflow-y-auto" x-ref="minuteList">
                        <template x-for="option in minutes" :key="'m-' + option.value">
                            <button
                                type="button"
                                class="btn btn-sm btn-block mb-1"
                                :class="option.value === minute ? 'btn-primary' : 'btn-ghost'"
                                :data-selected="option.value === minute"
                                x-text="option.label"
                                @click="pickMinute(option.value)"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-2 pt-2 border-t border-base-300">
                <x-button label="تأیید" class="btn-primary btn-sm" @click="open = false" />
            </div>
        </div>
    </div>

    @error($field)
        <div class="text-error text-sm mt-1">{{ $message }}</div>
    @enderror
</fieldset>
