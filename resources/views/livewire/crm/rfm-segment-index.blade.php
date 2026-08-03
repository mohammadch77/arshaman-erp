<div>
    <x-header title="بخش‌بندی مشتریان (RFM)" subtitle="مخاطبین شرکت جاری به تفکیک segment" separator />

    <x-alert title="یادآوری دقت" :icon="theme_icon('note')" class="alert-warning mb-4">
        این بخش‌بندی بر پایه تعاملات دستی‌ثبت‌شده است؛ دقتش وقتی سفارش‌های واقعی
        (فاز ۳) به‌طور خودکار ثبت شوند بالا می‌رود.
    </x-alert>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($segments as $segment)
            <x-card shadow class="!p-0">
                <x-slot:title class="text-sm">
                    {{ \App\Modules\CRM\Models\RfmSegment::segmentLabel($segment) }}
                    <x-badge :value="\App\Support\Farsi::toDigits(($groupedProfiles[$segment] ?? collect())->count())" class="badge-neutral badge-sm" />
                </x-slot:title>

                <div class="flex flex-col gap-3 px-4 pb-4">
                    @forelse (($groupedProfiles[$segment] ?? collect()) as $profile)
                        <div class="border border-base-300 rounded-box p-3">
                            <div class="font-semibold text-sm">
                                {{ $profile->contact?->full_name ?? 'بدون نام' }}
                            </div>

                            @if ($profile->rfmSegment?->recency_days !== null)
                                <div class="text-xs opacity-60">
                                    آخرین خرید: {{ \App\Support\Farsi::toDigits($profile->rfmSegment->recency_days) }} روز پیش
                                </div>
                                <div class="text-xs opacity-60">
                                    تعداد خرید: {{ \App\Support\Farsi::toDigits($profile->rfmSegment->frequency_count) }}
                                </div>
                                <div class="text-xs opacity-60">
                                    مبلغ کل:
                                    @if ($profile->rfmSegment->monetary_amount !== null)
                                        @toman($profile->rfmSegment->monetary_amount)
                                    @else
                                        ثبت نشده (تا اتصال سفارش واقعی)
                                    @endif
                                </div>
                            @else
                                <div class="text-xs opacity-50">هنوز خریدی ثبت نشده</div>
                            @endif

                            <x-button
                                label="محاسبه دوباره"
                                :icon="theme_icon('recalculate')"
                                class="btn-xs btn-outline mt-2"
                                wire:click="recalculate('{{ $profile->id }}')"
                                spinner="recalculate('{{ $profile->id }}')"
                            />
                        </div>
                    @empty
                        <div class="text-xs opacity-50">خالی</div>
                    @endforelse
                </div>
            </x-card>
        @endforeach
    </div>
</div>
