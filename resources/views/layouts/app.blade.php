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

                @if($activeCompany && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id))
                    <x-menu-item title="طرف‌حساب‌ها" :icon="theme_icon('party')" link="{{ route('parties.index') }}" />
                @endif

                @if(auth()->check())
                    <x-menu-item title="نرخ ارز" :icon="theme_icon('currency')" link="{{ route('exchange-rates.index') }}" />
                @endif

                @if($activeCompany && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id))
                    <x-menu-item title="سال‌های مالی" :icon="theme_icon('calendar')" link="{{ route('fiscal-periods.index') }}" />
                @endif

                {{-- فرایندها — «کارهای من»/«درخواست جدید»/«درخواست‌های من» در دسترس هر
                     نقشی در شرکت (بند ۴ صندوق کارهای من)، عمداً hasRoleInCompany($activeCompany->id)
                     بدون فهرست نقش، دقیقاً همان شرط ProcessDefinitionPolicy::viewAny.
                     «طراحی فرایندها» زیرمجموعه‌اش می‌ماند ولی با شرط جداگانه‌ی
                     holding_admin — دقیقاً همان ProcessDefinitionPolicy::create. --}}
                @if($activeCompany && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id))
                    <x-menu-sub title="فرایندها" :icon="theme_icon('process')">
                        <x-menu-item title="کارهای من" :icon="theme_icon('inbox')" link="{{ route('processes.tasks') }}" />
                        <x-menu-item title="درخواست جدید" :icon="theme_icon('add')" link="{{ route('processes.request') }}" />
                        <x-menu-item title="درخواست‌های من" :icon="theme_icon('history')" link="{{ route('processes.my-requests') }}" />
                        @if(auth()->user()->hasRoleInCompany($activeCompany->id, 'holding_admin'))
                            <x-menu-item title="طراحی فرایندها" :icon="theme_icon('template')" link="{{ route('processes.index') }}" />
                            <x-menu-item title="نظارت بر فرایندها" :icon="theme_icon('oversight')" link="{{ route('processes.oversight') }}" />
                        @endif
                    </x-menu-sub>
                @endif

                {{-- منابع انسانی — پنل ادمین/حسابدار. عمداً hasRoleInCompany($activeCompany->id, [...])
                     مقید به شرکت فعال است، نه hasRole() سراسری — دقیقاً همان شرطی که
                     EmployeePolicy/AttendancePolicy/LeavePolicy/PayrollPolicy::viewAny واقعاً
                     چک می‌کنند. hasRole() سراسری یعنی holding_admin شرکت دیگر هم آیتم منو را
                     می‌بیند ولی با کلیک ۴۰۳ می‌گیرد — همان الگوی نشتی نقش بند ۹/۱۱ CLAUDE.md،
                     این‌بار در لایه منو. --}}
                @if($activeCompany && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id, ['holding_admin', 'accountant']))
                    <x-menu-sub title="منابع انسانی" :icon="theme_icon('employee')">
                        <x-menu-item title="پرسنل" :icon="theme_icon('employee')" link="{{ route('employees.index') }}" />
                        <x-menu-item title="حضور و غیاب" :icon="theme_icon('attendance')" link="{{ route('attendance.index') }}" />
                        <x-menu-item title="جمع ماهانه کارکرد" :icon="theme_icon('report')" link="{{ route('attendance.monthly-summary') }}" />
                        <x-menu-item title="مرخصی‌ها" :icon="theme_icon('leave')" link="{{ route('leaves.index') }}" />
                        <x-menu-item title="حقوق و دستمزد" :icon="theme_icon('payroll')" link="{{ route('payroll.index') }}" />
                        <x-menu-item title="گزارش هزینه حقوق" :icon="theme_icon('report')" link="{{ route('payroll.expense-report') }}" />
                    </x-menu-sub>
                @endif

                {{-- مخاطبین — عمداً hasRoleInCompany($activeCompany->id, ['holding_admin', 'operator'])
                     است، همان دو نقش دقیق ContactSiteProfilePolicy/LeadPolicy/RfmSegmentPolicy::viewAny —
                     نه «هر نقشی در شرکت». viewer/accountant با شرط قبلی آیتم منو را می‌دیدند ولی
                     با کلیک ۴۰۳ می‌گرفتند. --}}
                {{-- محصولات — عمداً hasRoleInCompany($activeCompany->id) بدون فهرست نقش
                     است، همان شرط دقیق ProductPolicy::viewAny («هر نقشی در شرکت»). --}}
                @if($activeCompany && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id))
                    <x-menu-item title="محصولات" :icon="theme_icon('product')" link="{{ route('products.index') }}" />
                @endif

                @if($activeCompany && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id, ['holding_admin', 'operator']))
                    <x-menu-sub title="مخاطبین" :icon="theme_icon('crm')">
                        <x-menu-item title="فهرست مخاطبین" :icon="theme_icon('contact')" link="{{ route('contacts.index') }}" />
                        <x-menu-item title="مخاطب جدید" :icon="theme_icon('add')" link="{{ route('contacts.create') }}" />
                        <x-menu-item title="قیف فروش" :icon="theme_icon('lead')" link="{{ route('leads.index') }}" />
                        <x-menu-item title="بخش‌بندی RFM" :icon="theme_icon('segment')" link="{{ route('rfm-segments.index') }}" />
                        <x-menu-item title="کمپین‌ها" :icon="theme_icon('campaign')" link="{{ route('campaigns.index') }}" />
                    </x-menu-sub>
                @endif

                {{-- پنل خودِ کارمند — بدون نیاز به نقش کسب‌وکاری؛ فقط برای کاربری که واقعاً
                     یک پرونده پرسنلی مرتبط دارد (employees.user_id) نشان داده می‌شود، صرف‌نظر
                     از نقش/شرکت فعال — self-service طبق طراحی سند HR به نقش وابسته نیست.
                     withoutGlobalScopes چون کارمند لاگین‌شده ممکن است متعلق به شرکتی غیر از
                     شرکت فعال سوییچر باشد، همان الگوی MyAttendance/MyLeaves/MyPayslips::mount(). --}}
                @if(auth()->check() && \App\Modules\HR\Models\Employee::withoutGlobalScopes()->where('user_id', auth()->id())->exists())
                    <x-menu-sub title="پنل من" :icon="theme_icon('user')">
                        <x-menu-item title="حضور و غیاب من" :icon="theme_icon('attendance')" link="{{ route('my-attendance') }}" />
                        <x-menu-item title="مرخصی‌های من" :icon="theme_icon('leave')" link="{{ route('my-leaves') }}" />
                        <x-menu-item title="فیش‌های حقوقی من" :icon="theme_icon('payslip')" link="{{ route('my-payslips') }}" />
                    </x-menu-sub>
                @endif

                {{-- انبار — طبق بند ۵.۸ CLAUDE.md فقط برای شرکت‌های physical_goods/hybrid فعال است.
                     دسترسی داخل زیرمنو دقیقاً همان StockPolicy::viewAny («هر نقشی در شرکت»)،
                     همان الگوی محصولات. --}}
                @if($activeCompany && in_array($activeCompany->business_type->value, ['physical_goods', 'hybrid']) && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id))
                    <x-menu-sub title="انبار" :icon="theme_icon('inventory')">
                        <x-menu-item title="موجودی" :icon="theme_icon('inventory')" link="{{ route('inventory.stock.index') }}" />
                        <x-menu-item title="دریافت کالا" :icon="theme_icon('stock-in')" link="{{ route('inventory.receive') }}" />
                        <x-menu-item title="خروج کالا" :icon="theme_icon('stock-out')" link="{{ route('inventory.issue') }}" />
                        <x-menu-item title="کالاهای زیر نقطه سفارش" :icon="theme_icon('warning')" link="{{ route('inventory.low-stock-report') }}" />
                    </x-menu-sub>
                @endif

                @if($activeCompany && $activeCompany->business_type->value === 'project_services')
                    <x-menu-item title="پروژه‌ها" :icon="theme_icon('project')" link="#" no-wire-navigate />
                @endif

                {{-- سایت‌ساز — عمداً hasRoleInCompany($activeCompany->id) بدون فهرست نقش،
                     همان شرط دقیق PagePolicy::viewAny («هر نقشی در شرکت»). بدون محدودیت
                     business_type. وبلاگ (BlogPostPolicy::viewAny — همان شرط) و پیام‌های
                     تماس با ما (فقط holding_admin/operator — همان شرط دقیق قبلی زیر منوی
                     «مخاطبین») هم Session ۹ به همین زیرمنو منتقل شدند؛ فقط محل نمایش عوض
                     شد، هیچ Policy/route/controller دست نخورد. --}}
                @if($activeCompany && auth()->check() && auth()->user()->hasRoleInCompany($activeCompany->id))
                    <x-menu-sub title="سایت‌ساز" :icon="theme_icon('site-builder')">
                        <x-menu-item title="صفحات" :icon="theme_icon('page')" link="{{ route('sitebuilder.pages.index') }}" />
                        <x-menu-item title="تنظیمات سایت" :icon="theme_icon('settings')" link="{{ route('sitebuilder.settings') }}" />
                        <x-menu-item title="پست‌های وبلاگ" :icon="theme_icon('blog')" link="{{ route('blog.posts.index') }}" />
                        <x-menu-item title="دسته‌بندی‌های وبلاگ" :icon="theme_icon('category')" link="{{ route('blog.categories.index') }}" />
                        <x-menu-item title="برچسب‌های وبلاگ" :icon="theme_icon('blog-tag')" link="{{ route('blog.tags.index') }}" />

                        @if(auth()->user()->hasRoleInCompany($activeCompany->id, ['holding_admin', 'operator']))
                            <x-menu-item title="پیام‌های تماس با ما" :icon="theme_icon('inbox')" link="{{ route('contact-submissions.index') }}" />
                        @endif
                    </x-menu-sub>
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
