@php
    $companyContext = app(\App\Modules\Core\Services\CompanyContext::class);
    $activeCompany = auth()->check() ? $companyContext->activeCompany() : null;
    $isAggregateView = auth()->check() && $companyContext->isAggregateView();
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="arshaman">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/theme/favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

    {{-- NAVBAR (هدر سراسری، همه سایزها) --}}
    <x-nav sticky>
        <x-slot:brand>
            <x-app-brand class="lg:hidden" />
        </x-slot:brand>
        <x-slot:actions>
            <livewire:core.company-switcher />

            <label for="main-drawer" class="lg:hidden ms-3">
                <x-icon :name="theme_icon('menu')" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible collapse-text="جمع کردن منو" class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <x-app-brand class="px-5 pt-4" />

            {{-- MENU --}}
            <x-menu activate-by-route>

                <x-menu-item title="پیشخوان" :icon="theme_icon('dashboard')" link="/" exact />

                @if(auth()->check() && (auth()->user()->is_super_admin || auth()->user()->hasRole('holding_admin')))
                    <x-menu-item title="مدیریت کاربران" :icon="theme_icon('users')" link="{{ route('users.index') }}" />
                @endif

                {{-- منابع انسانی — پنل ادمین/حسابدار --}}
                @if(auth()->check() && (auth()->user()->is_super_admin || auth()->user()->hasRole('holding_admin') || auth()->user()->hasRole('accountant')))
                    <x-menu-sub title="منابع انسانی" :icon="theme_icon('employee')">
                        <x-menu-item title="پرسنل" :icon="theme_icon('employee')" link="{{ route('employees.index') }}" />
                        <x-menu-item title="حضور و غیاب" :icon="theme_icon('attendance')" link="{{ route('attendance.index') }}" />
                        <x-menu-item title="جمع ماهانه کارکرد" :icon="theme_icon('report')" link="{{ route('attendance.monthly-summary') }}" />
                        <x-menu-item title="مرخصی‌ها" :icon="theme_icon('leave')" link="{{ route('leaves.index') }}" />
                        <x-menu-item title="حقوق و دستمزد" :icon="theme_icon('payroll')" link="{{ route('payroll.index') }}" />
                        <x-menu-item title="گزارش هزینه حقوق" :icon="theme_icon('report')" link="{{ route('payroll.expense-report') }}" />
                    </x-menu-sub>
                @endif

                {{-- پنل خودِ کارمند — بدون نیاز به نقش؛ هر کاربری که پرونده پرسنلی
                     مرتبط داشته باشد. خود صفحه‌ها اگر پرونده‌ای نبود، پیام مناسب
                     نشان می‌دهند (نه خطا) — طبق Session 3 سند HR. --}}
                @if(auth()->check())
                    <x-menu-sub title="پنل من" :icon="theme_icon('user')">
                        <x-menu-item title="حضور و غیاب من" :icon="theme_icon('attendance')" link="{{ route('my-attendance') }}" />
                        <x-menu-item title="مرخصی‌های من" :icon="theme_icon('leave')" link="{{ route('my-leaves') }}" />
                        <x-menu-item title="فیش‌های حقوقی من" :icon="theme_icon('payslip')" link="{{ route('my-payslips') }}" />
                    </x-menu-sub>
                @endif

                @if($activeCompany && in_array($activeCompany->business_type->value, ['physical_goods', 'hybrid']))
                    <x-menu-item title="انبار" :icon="theme_icon('inventory')" link="#" no-wire-navigate />
                @endif

                @if($activeCompany && $activeCompany->business_type->value === 'project_services')
                    <x-menu-item title="پروژه‌ها" :icon="theme_icon('project')" link="#" no-wire-navigate />
                @endif

                {{-- User --}}
                @if($user = auth()->user())
                    <x-menu-separator />

                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                        <x-slot:actions>
                            <x-button :icon="theme_icon('logout')" class="btn-circle btn-ghost btn-xs" tooltip-left="خروج" no-wire-navigate link="/logout" />
                        </x-slot:actions>
                    </x-list-item>

                    <x-menu-separator />
                @endif
            </x-menu>
        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    {{--  TOAST area --}}
    <x-toast />
</body>
</html>
