@extends('layouts.public-site')

@php
    $faviconUrl = $siteSetting?->favicon_path
        ? '/storage/'.ltrim($siteSetting->favicon_path, '/')
        : asset('images/theme/favicon.svg');

    $displayTitle = $page->meta_title ?: ($siteSetting?->site_title ?: $company->name);
    $displayDescription = $page->meta_description ?: ($siteSetting?->site_tagline ?: '');
@endphp

@section('meta_title', $displayTitle)
@section('meta_description', $displayDescription)

@if (! empty($page->extra_css))
    @section('extra_css')
{!! $page->extra_css !!}
    @endsection
@endif

@if (! empty($page->extra_js))
    @section('extra_js')
{!! $page->extra_js !!}
    @endsection
@endif

@section('content')
    @if($preview ?? false)
        <div class="flex items-center justify-center gap-2 border border-warning bg-warning/10 p-3 text-center text-sm font-semibold text-base-content">
            <x-icon :name="theme_icon('preview')" class="h-5 w-5 text-warning" />
            این یک پیش‌نمایش است — این صفحه هنوز منتشر نشده یا این نسخه ذخیره‌نشده است.
        </div>
    @endif

    {!! $headerHtml !!}

    <main>
        {!! $bodyHtml !!}
    </main>

    {!! $footerHtml !!}
@endsection
