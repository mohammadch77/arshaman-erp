<div>
    <x-header title="گزارش هزینه حقوق" subtitle="جمع خالص فیش‌های نهایی‌شده ماه، به تفکیک شرکت" separator>
        <x-slot:actions>
            <x-select
                wire:model.live="year"
                :options="\App\Support\Jalali::yearOptions()"
                option-value="id"
                option-label="name"
                placeholder="سال"
            />
            <x-select
                wire:model.live="month"
                :options="\App\Support\Jalali::monthOptions()"
                option-value="id"
                option-label="name"
                placeholder="ماه"
            />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-4">
        <x-alert :icon="theme_icon('warning')" class="alert-warning">
            <div class="text-sm leading-relaxed">
                این گزارش موقتی است و صرفاً نمایشی — عدد آن هنوز به‌عنوان سند هزینه در دفتر کل ثبت
                نشده. تا ساخته‌شدن ماژول هزینه‌ها، اتصال خودکار انجام نمی‌شود.
            </div>
        </x-alert>

        @if($rows->isEmpty())
            <x-card shadow>
                <div class="flex items-center gap-2 text-base-content/70">
                    <x-icon :name="theme_icon('warning')" class="w-5 h-5" />
                    <span>برای این ماه هیچ دوره حقوق نهایی‌شده‌ای در هیچ شرکتی وجود ندارد.</span>
                </div>
            </x-card>
        @else
            <x-card shadow>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-icon :name="theme_icon('report')" class="w-6 h-6 text-primary" />
                        <div class="font-semibold">
                            دوره {{ \App\Support\Farsi::toDigits($this->periodMonth) }} — جمع همه شرکت‌ها
                        </div>
                    </div>

                    <div class="text-sm text-base-content/60">
                        جمع کل خالص:
                        <span class="font-semibold text-base-content">
                            {{ \App\Support\Farsi::toToman($this->grandTotal) }}
                        </span>
                    </div>
                </div>
            </x-card>

            @if($this->totalReviewCount > 0)
                <x-alert :icon="theme_icon('review')" class="alert-error">
                    <x-slot:title>
                        {{ \App\Support\Farsi::toDigits($this->totalReviewCount) }} فیش در این ماه نیاز به بررسی دستی
                        حسابدار دارد
                    </x-slot:title>

                    <div class="text-sm leading-relaxed">
                        در این فیش‌ها کسورات از حقوق و مزایا بیشتر شده و مبلغ قابل پرداخت صفر لحاظ شده —
                        جمع کل بالا ممکن است کامل نباشد تا این فیش‌ها بررسی شوند.
                    </div>
                </x-alert>
            @endif

            <x-card shadow>
                <x-table
                    :headers="[
                        ['key' => 'company', 'label' => 'شرکت'],
                        ['key' => 'payslip_count', 'label' => 'تعداد فیش'],
                        ['key' => 'total_net', 'label' => 'جمع خالص'],
                        ['key' => 'review', 'label' => 'نیاز به بررسی'],
                    ]"
                    :rows="$rows"
                >
                    @scope('cell_company', $row)
                        {{ $row['company']?->name ?? '—' }}
                    @endscope

                    @scope('cell_payslip_count', $row)
                        {{ \App\Support\Farsi::toDigits($row['payslip_count']) }}
                    @endscope

                    @scope('cell_total_net', $row)
                        <span class="font-semibold">{{ \App\Support\Farsi::toToman($row['total_net']) }}</span>
                    @endscope

                    @scope('cell_review', $row)
                        @if($row['review_count'] > 0)
                            <x-badge
                                value="{{ \App\Support\Farsi::toDigits($row['review_count']) }} فیش"
                                class="badge-error badge-sm"
                                tooltip="{{ $row['review_names']->implode('، ') }}"
                            />
                        @else
                            <span class="text-base-content/50">—</span>
                        @endif
                    @endscope
                </x-table>
            </x-card>
        @endif
    </div>
</div>
