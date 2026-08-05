<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="arshaman">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">

    <title>@yield('meta_title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', '')">

    <meta property="og:title" content="@yield('meta_title', config('app.name'))">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:type" content="@yield('meta_type', 'website')">
    @hasSection('meta_image')
        <meta property="og:image" content="@yield('meta_image')">
    @endif

    <link rel="icon" href="{{ asset('images/theme/favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

    <header class="border-b border-base-300 bg-base-100">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
            <a href="{{ route('public-blog.index', $company->slug) }}" class="flex items-center gap-3">
                <x-app-logo size="small" />
                <span class="text-lg font-bold text-base-content">{{ $company->name }}</span>
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-10">
        @yield('content')
    </main>

    <footer class="mt-12 border-t border-base-300 py-6 text-center text-sm text-base-content/60">
        {{ $company->name }} — {{ \App\Support\Farsi::toDigits(now()->year) }}
    </footer>

    <x-toast />
</body>
</html>
