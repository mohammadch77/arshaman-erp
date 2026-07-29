@props(['payslip'])

{{--
    هشدار «نیاز به بررسی دستی» — فقط وقتی خالص فیش در صفر clamp شده است.
    پرشدن raw_net_amount یعنی کسورات از مجموع حقوق و مزایا بیشتر شده؛ مبلغ
    قابل پرداخت صفر است ولی خود فیش نباید بی‌صدا رد شود.
--}}
@if($payslip->needsManualReview())
    <x-alert :icon="theme_icon('review')" class="alert-error">
        <x-slot:title>این فیش به‌خاطر غیبت/کسورات زیاد نیاز به بررسی دستی حسابدار دارد</x-slot:title>

        <div class="text-sm leading-relaxed">
            مجموع کسورات از حقوق و مزایای این دوره بیشتر شده است. مبلغ قابل پرداخت
            صفر در نظر گرفته شده، ولی مبلغ خام محاسبه‌شده
            <span class="font-semibold">{{ \App\Support\Farsi::toToman((string) $payslip->raw_net_amount) }}</span>
            بوده است.
        </div>
    </x-alert>
@endif
