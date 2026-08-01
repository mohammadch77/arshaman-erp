<div>
    <x-header title="حقوق و دستمزد" subtitle="محاسبه حقوق ماهانه، فهرست فیش‌ها و نهایی‌کردن دوره" separator>
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

            @if(! $run?->isLocked())
                <x-button
                    label="محاسبه حقوق"
                    :icon="theme_icon('calculate')"
                    class="btn-primary"
                    wire:click="calculate"
                    spinner="calculate"
                    responsive
                />
            @endif

            @if($run && ! $run->isLocked() && $payslips->isNotEmpty())
                <x-button
                    label="نهایی‌کردن دوره"
                    :icon="theme_icon('finalize')"
                    class="btn-warning"
                    wire:click="finalize"
                    spinner="finalize"
                    wire:confirm="بعد از نهایی‌کردن، هیچ فیش این دوره قابل تغییر یا بازمحاسبه نیست. ادامه می‌دهید؟"
                    responsive
                />
            @endif

            {{-- دوره نهایی‌شده به‌جای دکمه‌های غیرفعال، یک مسیر اصلاح صریح دارد:
                 بازگشایی ثبت‌شده با دلیل. ویرایش مستقیم فیش قفل‌شده همچنان ممنوع است. --}}
            @if($run?->isLocked())
                <x-button
                    label="ویرایش دوره"
                    :icon="theme_icon('reopen')"
                    class="btn-outline"
                    wire:click="openReopen"
                    responsive
                />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid gap-4">
        <x-payroll-provisional-notice />

        @if(! $run)
            <x-card shadow>
                <div class="flex items-center gap-2 text-base-content/70">
                    <x-icon :name="theme_icon('warning')" class="w-5 h-5" />
                    <span>برای این ماه هنوز حقوقی محاسبه نشده است. پیش از محاسبه، «جمع ماهانه کارکرد» همین ماه باید برای همه کارمندان فعال محاسبه شده باشد.</span>
                </div>
            </x-card>
        @else
            <x-card shadow>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-icon :name="theme_icon('payroll')" class="w-6 h-6 text-primary" />
                        <div>
                            <div class="font-semibold">دوره {{ \App\Support\Farsi::toDigits($this->periodMonth) }}</div>
                            <div class="text-sm text-base-content/60">
                                @if($run->calculated_at)
                                    آخرین محاسبه: {{ \App\Support\Jalali::toDisplayDateTime($run->calculated_at) }}
                                @endif
                                @if($run->finalized_at)
                                    — نهایی‌شده در {{ \App\Support\Jalali::toDisplayDateTime($run->finalized_at) }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="text-sm text-base-content/60">
                            جمع خالص:
                            <span class="font-semibold text-base-content">
                                {{ \App\Support\Farsi::toToman($this->totalNet) }}
                            </span>
                        </div>
                        <x-badge
                            value="{{ $run->payroll_status->label() }}"
                            class="{{ match($run->payroll_status->value) {
                                'finalized' => 'badge-success',
                                'calculated' => 'badge-info',
                                default => 'badge-ghost',
                            } }}"
                        />
                    </div>
                </div>
            </x-card>

            @php($needingReview = $payslips->filter->needsManualReview())

            @if($needingReview->isNotEmpty())
                <x-alert :icon="theme_icon('review')" class="alert-error">
                    <x-slot:title>
                        {{ \App\Support\Farsi::toDigits($needingReview->count()) }} فیش نیاز به بررسی دستی حسابدار دارد
                    </x-slot:title>

                    <div class="text-sm leading-relaxed">
                        در این فیش‌ها کسورات از حقوق و مزایا بیشتر شده و مبلغ قابل پرداخت صفر
                        در نظر گرفته شده است:
                        <span class="font-semibold">{{ $needingReview->map(fn ($payslip) => $payslip->employee?->full_name)->filter()->implode('، ') }}</span>
                    </div>
                </x-alert>
            @endif

            <x-card shadow>
                <x-table
                    :headers="[
                        ['key' => 'employee', 'label' => 'کارمند'],
                        ['key' => 'gross_salary_amount', 'label' => 'حقوق پایه'],
                        ['key' => 'overtime_amount', 'label' => 'اضافه‌کاری'],
                        ['key' => 'benefits_amount', 'label' => 'مزایا'],
                        ['key' => 'deductions', 'label' => 'کسورات'],
                        ['key' => 'net_amount', 'label' => 'خالص پرداختی'],
                    ]"
                    :rows="$payslips"
                >
                    @scope('cell_employee', $payslip)
                        {{ $payslip->employee?->full_name ?? '—' }}
                    @endscope

                    @scope('cell_gross_salary_amount', $payslip)
                        {{ \App\Support\Farsi::toToman((string) $payslip->gross_salary_amount) }}
                    @endscope

                    @scope('cell_overtime_amount', $payslip)
                        {{ \App\Support\Farsi::toToman((string) $payslip->overtime_amount) }}
                    @endscope

                    @scope('cell_benefits_amount', $payslip)
                        {{ \App\Support\Farsi::toToman((string) $payslip->benefits_amount) }}
                    @endscope

                    @scope('cell_deductions', $payslip)
                        <span class="text-error">{{ \App\Support\Farsi::toToman($payslip->totalDeductions()) }}</span>
                    @endscope

                    @scope('cell_net_amount', $payslip)
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ \App\Support\Farsi::toToman((string) $payslip->net_amount) }}</span>
                            @if($payslip->needsManualReview())
                                <x-badge
                                    value="نیاز به بررسی"
                                    class="badge-error badge-sm"
                                    tooltip="مبلغ خام محاسبه‌شده: {{ \App\Support\Farsi::toToman((string) $payslip->raw_net_amount) }}"
                                />
                            @endif
                        </div>
                    @endscope
                </x-table>
            </x-card>
        @endif
    </div>

    <x-modal wire:model="showReopenModal" title="بازگشایی دوره نهایی‌شده" separator>
        <div class="grid gap-4">
            <x-alert :icon="theme_icon('warning')" class="alert-warning">
                <div class="text-sm leading-relaxed">
                    با بازگشایی، این دوره به وضعیت «پیش‌نویس» برمی‌گردد و قفل مالی آن برداشته می‌شود.
                    برای اعمال داده جدید باید <span class="font-semibold">دوباره محاسبه</span> کنید و
                    سپس <span class="font-semibold">دوباره نهایی</span> کنید — تا آن زمان دوره قفل نیست.
                    این عملیات با نام شما و دلیلی که می‌نویسید ثبت می‌شود.
                </div>
            </x-alert>

            <x-textarea
                label="دلیل بازگشایی"
                wire:model="reopenReason"
                hint="مثلاً: کارکرد ماهانه اصلاح شد و باید حقوق دوباره محاسبه شود."
                rows="3"
                required
            />
        </div>

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.showReopenModal = false" />
            <x-button label="بازگشایی دوره" class="btn-warning" wire:click="reopen" spinner="reopen" />
        </x-slot:actions>
    </x-modal>
</div>
