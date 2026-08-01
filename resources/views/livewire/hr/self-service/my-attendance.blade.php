@php
    $formatMinutes = function (int $minutes): string {
        $hours = intdiv(abs($minutes), 60);
        $rest = abs($minutes) % 60;

        return match (true) {
            $hours > 0 && $rest > 0 => \App\Support\Farsi::toDigits($hours).' ساعت و '.\App\Support\Farsi::toDigits($rest).' دقیقه',
            $hours > 0 => \App\Support\Farsi::toDigits($hours).' ساعت',
            default => \App\Support\Farsi::toDigits($rest).' دقیقه',
        };
    };
@endphp

<div>
    <x-header title="حضور و غیاب من" subtitle="ثبت ورود و خروج" separator />

    @if(! $employeeId)
        <x-card shadow>
            <div class="flex items-center gap-2 text-base-content/70">
                <x-icon :name="theme_icon('warning')" class="w-5 h-5" />
                <span>شما پرونده پرسنلی ندارید. برای ثبت حضور و غیاب باید ابتدا به یک پرونده کارمندی وصل شوید.</span>
            </div>
        </x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- لاگ امروز --}}
            <x-card shadow>
                <x-slot:title>{{ $employeeFullName }}</x-slot:title>
                <x-slot:subtitle>{{ \App\Support\Jalali::toDisplay(now()) }}</x-slot:subtitle>

                <div class="grid gap-4">
                    @if($todayPunches->isEmpty())
                        <div class="flex items-center gap-2 text-base-content/70">
                            <x-icon :name="theme_icon('attendance')" class="w-5 h-5" />
                            <span>امروز هنوز ترددی ثبت نشده است.</span>
                        </div>
                    @else
                        {{-- ظرف اسکرول‌دار: با زیاد شدن تعداد ترددها فقط همین
                             فهرست اسکرول می‌خورد، نه کل صفحه. --}}
                        <div class="max-h-64 overflow-y-auto pe-1">
                            <ul class="divide-y divide-base-300">
                                @foreach($todayPunches as $punch)
                                    <li class="flex items-center justify-between py-2">
                                        <div class="flex items-center gap-2">
                                            <x-icon :name="theme_icon('check-in')" class="w-4 h-4 text-base-content/60" />
                                            <span>{{ \App\Support\Jalali::toDisplayTime($punch->check_in_at) }}</span>
                                            <span class="text-base-content/40">تا</span>
                                            @if($punch->check_out_at)
                                                <span>{{ \App\Support\Jalali::toDisplayTime($punch->check_out_at) }}</span>
                                            @else
                                                <x-badge value="در حال کار" class="badge-info badge-sm" />
                                            @endif
                                        </div>

                                        <span class="text-sm text-base-content/70">
                                            {{ $punch->duration_minutes !== null ? $formatMinutes($punch->duration_minutes) : '—' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="flex items-center justify-between border-t border-base-300 pt-3">
                            <span class="font-semibold">جمع کارکرد امروز</span>
                            <span class="font-semibold">{{ $formatMinutes($this->todayWorkedMinutes) }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-base-content/70">اختلاف</span>
                            @if($this->todayIsClosed)
                                <x-attendance-balance :minutes="$this->todayBalance" />
                            @else
                                <span class="text-sm text-base-content/60">تا ثبت خروج محاسبه نمی‌شود</span>
                            @endif
                        </div>
                    @endif

                    {{-- دقیقاً یکی از دو دکمه فعال است. هیچ فیلد ورودی‌ای وجود
                         ندارد: تاریخ و ساعت از ساعت سرور خوانده می‌شوند. --}}
                    <div class="flex gap-3 border-t border-base-300 pt-4">
                        <x-button
                            label="ثبت ورود"
                            :icon="theme_icon('check-in')"
                            class="btn-primary flex-1"
                            wire:click="checkIn"
                            spinner="checkIn"
                            :disabled="$this->hasOpenPunch"
                        />
                        <x-button
                            label="ثبت خروج"
                            :icon="theme_icon('check-out')"
                            class="btn-secondary flex-1"
                            wire:click="checkOut"
                            spinner="checkOut"
                            :disabled="! $this->hasOpenPunch"
                        />
                    </div>
                </div>
            </x-card>

            {{-- ماه جاری، گروه‌بندی‌شده بر اساس روز --}}
            <x-card shadow>
                <x-slot:title>ترددهای این ماه</x-slot:title>

                @if($punchesByDay->isEmpty())
                    <div class="text-base-content/70">ترددی در این ماه ثبت نشده است.</div>
                @else
                    {{-- همان قاعده: فهرست ماه در ظرف خودش اسکرول می‌خورد. --}}
                    <div class="grid gap-3 max-h-96 overflow-y-auto pe-1">
                        @foreach($punchesByDay as $date => $punches)
                            @php($dayBalance = app(\App\Modules\HR\Services\AttendanceCalculator::class)->balanceForDay($punches))
                            @php($dayClosed = ! app(\App\Modules\HR\Services\AttendanceCalculator::class)->hasOpenPunch($punches))

                            <div class="rounded-box bg-base-200 p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium">{{ \App\Support\Jalali::toDisplay($date) }}</span>
                                    @if($dayClosed)
                                        <x-attendance-balance :minutes="$dayBalance" />
                                    @else
                                        <x-badge value="باز" class="badge-info badge-sm" />
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 text-sm text-base-content/70">
                                    @foreach($punches as $punch)
                                        <span class="badge badge-ghost">
                                            {{ \App\Support\Jalali::toDisplayTime($punch->check_in_at) }}
                                            تا
                                            {{ $punch->check_out_at ? \App\Support\Jalali::toDisplayTime($punch->check_out_at) : 'ــ' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    @endif
</div>
