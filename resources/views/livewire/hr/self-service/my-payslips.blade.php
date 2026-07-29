<div>
    <div class="print:hidden">
        <x-header title="فیش‌های حقوقی من" subtitle="فیش‌های دوره‌های نهایی‌شده" separator />
    </div>

    @if(! $employeeId)
        <x-card shadow>
            <div class="flex items-center gap-2 text-base-content/70">
                <x-icon :name="theme_icon('warning')" class="w-5 h-5" />
                <span>شما پرونده پرسنلی ندارید. برای دیدن فیش حقوقی باید ابتدا به یک پرونده کارمندی وصل شوید.</span>
            </div>
        </x-card>
    @else
        <div class="grid gap-4">
            <div class="print:hidden">
                <x-card shadow>
                    @if($payslips->isEmpty())
                        <div class="flex items-center gap-2 text-base-content/70">
                            <x-icon :name="theme_icon('payslip')" class="w-5 h-5" />
                            <span>هنوز هیچ فیش حقوقی نهایی‌شده‌ای برای شما صادر نشده است.</span>
                        </div>
                    @else
                        <x-table
                            :headers="[
                                ['key' => 'period', 'label' => 'دوره'],
                                ['key' => 'net_amount', 'label' => 'خالص پرداختی'],
                                ['key' => 'actions', 'label' => ''],
                            ]"
                            :rows="$payslips"
                        >
                            @scope('cell_period', $payslip)
                                {{ \App\Support\Farsi::toDigits($payslip->payrollRun->period_month) }}
                            @endscope

                            @scope('cell_net_amount', $payslip)
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ \App\Support\Farsi::toToman((string) $payslip->net_amount) }}</span>
                                    @if($payslip->needsManualReview())
                                        <x-badge value="نیاز به بررسی" class="badge-error badge-sm" />
                                    @endif
                                </div>
                            @endscope

                            @scope('cell_actions', $payslip)
                                <x-button
                                    label="مشاهده فیش"
                                    :icon="theme_icon('payslip')"
                                    class="btn-ghost btn-sm"
                                    wire:click="select('{{ $payslip->id }}')"
                                />
                            @endscope
                        </x-table>
                    @endif
                </x-card>
            </div>

            @if($selectedPayslip)
                <x-card shadow separator>
                    <x-slot:title>
                        فیش حقوقی دوره {{ \App\Support\Farsi::toDigits($selectedPayslip->payrollRun->period_month) }}
                    </x-slot:title>

                    <x-slot:menu>
                        <div class="print:hidden">
                            <x-button
                                label="چاپ"
                                :icon="theme_icon('print')"
                                class="btn-primary btn-sm"
                                onclick="window.print()"
                            />
                        </div>
                    </x-slot:menu>

                    <div class="grid gap-4">
                        <x-payslip-review-notice :payslip="$selectedPayslip" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-base-content/60">نام:</span>
                                <span class="font-semibold">{{ $selectedPayslip->employee->full_name }}</span>
                            </div>
                            <div>
                                <span class="text-base-content/60">سمت:</span>
                                <span class="font-semibold">{{ $selectedPayslip->employee->position }}</span>
                            </div>
                        </div>

                        <x-table
                            :headers="[
                                ['key' => 'title', 'label' => 'شرح'],
                                ['key' => 'amount', 'label' => 'مبلغ'],
                            ]"
                            :rows="[
                                ['title' => 'حقوق پایه', 'amount' => (string) $selectedPayslip->gross_salary_amount, 'kind' => 'add'],
                                ['title' => 'اضافه‌کاری', 'amount' => (string) $selectedPayslip->overtime_amount, 'kind' => 'add'],
                                ['title' => 'مزایا', 'amount' => (string) $selectedPayslip->benefits_amount, 'kind' => 'add'],
                                ['title' => 'کسر غیبت', 'amount' => (string) $selectedPayslip->absence_deduction_amount, 'kind' => 'sub'],
                                ['title' => 'کسر مرخصی بدون‌حقوق', 'amount' => (string) $selectedPayslip->unpaid_leave_deduction_amount, 'kind' => 'sub'],
                                ['title' => 'بیمه (سهم کارمند)', 'amount' => (string) $selectedPayslip->insurance_amount, 'kind' => 'sub'],
                                ['title' => 'مالیات', 'amount' => (string) $selectedPayslip->tax_amount, 'kind' => 'sub'],
                            ]"
                        >
                            @scope('cell_title', $row)
                                {{ $row['title'] }}
                            @endscope

                            @scope('cell_amount', $row)
                                <span class="{{ $row['kind'] === 'sub' ? 'text-error' : '' }}">
                                    {{ $row['kind'] === 'sub' ? '−' : '' }}{{ \App\Support\Farsi::toToman($row['amount']) }}
                                </span>
                            @endscope
                        </x-table>

                        <div class="flex items-center justify-between border-t border-base-300 pt-4">
                            <span class="font-semibold">خالص پرداختی</span>
                            <span class="text-lg font-bold text-primary">
                                {{ \App\Support\Farsi::toToman((string) $selectedPayslip->net_amount) }}
                            </span>
                        </div>

                        <x-payroll-provisional-notice />
                    </div>
                </x-card>
            @endif
        </div>
    @endif
</div>
