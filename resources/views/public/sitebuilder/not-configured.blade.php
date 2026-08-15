@extends('layouts.public-site')

@php
    $faviconUrl = asset('images/theme/favicon.svg');
@endphp

@section('meta_title', $company->name)

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4">
        <x-card shadow class="max-w-md text-center">
            <x-icon :name="theme_icon('site-builder')" class="mx-auto mb-4 h-12 w-12 text-base-content/40" />
            <h1 class="text-lg font-bold text-base-content">{{ $company->name }}</h1>
            <p class="mt-2 text-sm text-base-content/60">
                سایت این مجموعه هنوز راه‌اندازی نشده است.
            </p>
        </x-card>
    </div>
@endsection
