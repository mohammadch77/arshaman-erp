{{--
    هشدار «فرمول موقت» — روی هر صفحه‌ای که مبلغ حقوق نشان می‌دهد.
    طبق docs/PROJECT_02_HR.md بند ۴ «نکات حیاتی»، موقت‌بودن این فرمول‌ها باید در
    خروجی هم دیده شود، نه فقط در کامنت کد. سه موردی که واقعاً موقت‌اند:
    بیمه، مالیات، و مخرج نرخ روزانه (تصمیم B، Session 6).
--}}
<x-alert :icon="theme_icon('warning')" class="alert-warning">
    <x-slot:title>فرمول‌های موقت — نیازمند تأیید حسابدار واقعی</x-slot:title>

    <div class="text-sm leading-relaxed">
        مبالغ این صفحه با فرمول‌های موقت محاسبه شده‌اند و مبنای پرداخت قطعی نیستند:
        <ul class="list-disc mt-2 space-y-1 ps-5">
            <li>
                <span class="font-semibold">بیمه:</span>
                درصد ثابت {{ \App\Support\Farsi::toDigits((int) (config('payroll.insurance_employee_rate') * 100)) }}٪
                سهم کارمند — نه محاسبه دقیق طبق قانون کار.
            </li>
            <li>
                <span class="font-semibold">مالیات:</span>
                یک سقف معافیت و یک نرخ ثابت روی مازاد — نه پلکان چندنرخی قانون مالیات‌های مستقیم.
            </li>
            <li>
                <span class="font-semibold">نرخ روزانه (مبنای کسر غیبت و مرخصی بدون‌حقوق):</span>
                حقوق پایه تقسیم بر {{ \App\Support\Farsi::toDigits(config('payroll.standard_monthly_days')) }} روز
                — گزینه‌های دیگر (۳۰ روز تقویمی، یا روزهای کاری واقعی همان ماه) هنوز تعیین تکلیف نشده‌اند.
            </li>
        </ul>
    </div>
</x-alert>
