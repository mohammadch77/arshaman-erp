<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="arshaman">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">

    <title>@yield('meta_title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', '')">

    <meta property="og:title" content="@yield('meta_title', config('app.name'))">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:type" content="website">

    <link rel="icon" href="{{ $faviconUrl ?? asset('images/theme/favicon.svg') }}">

    {{-- کتابخانه‌ی ثابت فونت‌های فارسی‌خوان دموهای سایت‌ساز — نگاه کن
         WidgetContentRenderer (theme.font_family/heading_font). یک لینک واحد
         با همه خانواده‌های مجاز، صرف‌نظر از اینکه این دمو مشخص کدام را
         استفاده می‌کند — چون فونت از یک enum بسته در دیتای دمو می‌آید، نه
         ورودی آزاد، لود همه‌ی آن‌ها یک‌جا امن است و نیازی به تزریق پویا نیست. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&family=Cairo:wght@400;600;700;800&family=Markazi+Text:wght@500;600;700&family=Noto+Naskh+Arabic:wght@500;600;700&family=Lalezar&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @hasSection('extra_css')
        <style>@yield('extra_css')</style>
    @endif
</head>
<body class="min-h-screen bg-base-100 font-sans antialiased">
    @yield('content')

    @hasSection('extra_js')
        <script>@yield('extra_js')</script>
    @endif
</body>
</html>
