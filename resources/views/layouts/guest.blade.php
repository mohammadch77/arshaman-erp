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

    <div class="flex min-h-screen items-center justify-center bg-gradient-to-b from-primary/5 via-base-200 to-base-200 px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex justify-center">
                <x-app-logo />
            </div>

            {{ $slot }}
        </div>
    </div>

    <x-toast />
</body>
</html>
